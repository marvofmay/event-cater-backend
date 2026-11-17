<?php

declare(strict_types=1);

namespace App\Module\Support\Application;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Support\Application\Command\ChangeTicketStatus;
use App\Module\Support\Application\Command\OpenTicket;
use App\Module\Support\Application\Command\ReplyToTicket;
use App\Module\Support\Application\Port\AttachmentStorage;
use App\Module\Support\Application\Port\TicketStore;
use App\Module\Support\Domain\Category;
use App\Module\Support\Domain\TicketPolicy;
use App\Module\Support\Domain\TicketStatus;
use Ramsey\Uuid\Uuid;

/**
 * Support desk use cases. Commands change the ticket; queries only read it.
 * The store and file storage are ports, so the rules do not depend on MySQL or the disk.
 */
final readonly class SupportDesk
{
    public function __construct(private TicketStore $tickets, private AttachmentStorage $files)
    {
    }

    /** @return list<array{value: string, label: string}> */
    public function categories(): array
    {
        return Category::options();
    }

    /** @return list<array{value: string, label: string}> */
    public function statuses(): array
    {
        return TicketStatus::options();
    }

    public function open(OpenTicket $command): string
    {
        $category = TicketPolicy::assertCategory($command->category);
        $subject = TicketPolicy::subject($command->subject);
        TicketPolicy::assertAttachmentCount(count($command->files));
        $message = TicketPolicy::message($command->message, $command->files === []);
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-1 hour')->format('Y-m-d H:i:s');
        if ($this->tickets->countRecent($command->reporter->tenantUuid, $command->reporter->userUuid, $since) >= TicketPolicy::HOURLY_LIMIT) {
            throw new BusinessRuleViolation('Wysłano zbyt wiele zgłoszeń w ciągu godziny. Spróbuj później.');
        }

        $stored = $this->files->accept($command->files);
        $now = $this->now();
        $ticketUuid = Uuid::uuid4()->toString();
        $messageUuid = Uuid::uuid4()->toString();
        try {
            $this->tickets->add(
                [
                    'uuid' => $ticketUuid,
                    'tenant_uuid' => $command->reporter->tenantUuid,
                    'tenant_slug' => $command->reporter->tenantSlug,
                    'tenant_name' => mb_substr($command->reporter->tenantName, 0, 200),
                    'author_uuid' => $command->reporter->userUuid,
                    'author_email' => mb_substr($command->reporter->email, 0, 180),
                    'author_name' => mb_substr($command->reporter->name, 0, 200),
                    'category' => $category->value,
                    'subject' => $subject,
                    'status' => TicketStatus::Open->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $this->messageRow($messageUuid, $ticketUuid, 'user', $command->reporter->userUuid, $command->reporter->name, $message, $now),
                $this->attachmentRows($ticketUuid, $messageUuid, $stored, $now),
            );
        } catch (\Throwable $error) {
            $this->files->discard(array_column($stored, 'storage_key'));
            throw $error;
        }

        return $ticketUuid;
    }

    /** @return list<array<string, mixed>> */
    public function listMine(Reporter $reporter): array
    {
        return array_map($this->listItem(...), $this->tickets->listForAuthor($reporter->tenantUuid, $reporter->userUuid));
    }

    /** @return array<string, mixed> */
    public function getMine(Reporter $reporter, string $ticketUuid): array
    {
        return $this->detail($this->tickets->findForAuthor($reporter->tenantUuid, $reporter->userUuid, $ticketUuid));
    }

    /** @return array<string, mixed> */
    public function replyAsUser(Reporter $reporter, string $ticketUuid, string $message): array
    {
        $ticket = $this->tickets->findForAuthor($reporter->tenantUuid, $reporter->userUuid, $ticketUuid);
        if ($ticket === null) {
            throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
        }
        $body = TicketPolicy::message($message, true);
        $now = $this->now();
        $this->tickets->addMessage(
            $ticketUuid,
            $this->messageRow(Uuid::uuid4()->toString(), $ticketUuid, 'user', $reporter->userUuid, $reporter->name, $body, $now),
            TicketPolicy::statusAfterUserReply((string) $ticket['status']),
            $now,
        );

        return $this->getMine($reporter, $ticketUuid);
    }

    /** @return list<array<string, mixed>> */
    public function listForOperator(?string $status, ?string $category, ?string $search): array
    {
        if ($status !== null && $status !== '') {
            TicketPolicy::assertStatus($status);
        } else {
            $status = null;
        }
        if ($category !== null && $category !== '') {
            TicketPolicy::assertCategory($category);
        } else {
            $category = null;
        }
        $search = trim((string) $search);
        if (mb_strlen($search) > 80) {
            throw new BusinessRuleViolation('Szukana fraza może mieć najwyżej 80 znaków.');
        }

        return array_map($this->listItem(...), $this->tickets->listForOperator($status, $category, $search === '' ? null : $search));
    }

    /** @return array<string, mixed> */
    public function getForOperator(string $ticketUuid): array
    {
        return $this->detail($this->tickets->find($ticketUuid));
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $counts = $this->tickets->summary();
        foreach (TicketStatus::cases() as $status) {
            $counts[$status->value] ??= 0;
        }

        return [
            'open' => $counts[TicketStatus::Open->value],
            'inProgress' => $counts[TicketStatus::InProgress->value],
            'waiting' => $counts[TicketStatus::Waiting->value],
            'resolved' => $counts[TicketStatus::Resolved->value],
            'closed' => $counts[TicketStatus::Closed->value],
            'attention' => $counts[TicketStatus::Open->value] + $counts[TicketStatus::InProgress->value],
        ];
    }

    /** @return array<string, mixed> */
    public function replyAsOperator(ReplyToTicket $command): array
    {
        if ($command->role !== 'operator') {
            throw new \LogicException('Operator reply expected.');
        }
        $ticket = $this->tickets->find($command->ticketUuid);
        if ($ticket === null) {
            throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
        }
        $status = $command->status === null || $command->status === ''
            ? (string) $ticket['status']
            : TicketPolicy::assertStatus($command->status)->value;
        $now = $this->now();
        $this->tickets->addMessage(
            $command->ticketUuid,
            $this->messageRow(Uuid::uuid4()->toString(), $command->ticketUuid, 'operator', $command->authorUuid, $command->authorLabel, TicketPolicy::message($command->message, true), $now),
            $status,
            $now,
        );

        return $this->getForOperator($command->ticketUuid);
    }

    /** @return array<string, mixed> */
    public function changeStatus(ChangeTicketStatus $command): array
    {
        $status = TicketPolicy::assertStatus($command->status);
        $ticket = $this->tickets->find($command->ticketUuid);
        if ($ticket === null) {
            throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
        }
        if ($ticket['status'] !== $status->value) {
            $this->tickets->updateStatus($command->ticketUuid, $status->value, $this->now());
        }

        return $this->getForOperator($command->ticketUuid);
    }

    /** @return array{path: string, name: string, mime: string} */
    public function downloadForUser(Reporter $reporter, string $ticketUuid, string $attachmentUuid): array
    {
        $row = $this->tickets->attachment($ticketUuid, $attachmentUuid);
        if ($row === null || $row['tenant_uuid'] !== $reporter->tenantUuid || $row['author_uuid'] !== $reporter->userUuid) {
            throw new PlanningResourceNotFound('Nie znaleziono załącznika.');
        }

        return $this->file($row);
    }

    /** @return array{path: string, name: string, mime: string} */
    public function downloadForOperator(string $ticketUuid, string $attachmentUuid): array
    {
        $row = $this->tickets->attachment($ticketUuid, $attachmentUuid);
        if ($row === null) {
            throw new PlanningResourceNotFound('Nie znaleziono załącznika.');
        }

        return $this->file($row);
    }

    /** @param array<string, mixed>|null $ticket */
    private function detail(?array $ticket): array
    {
        if ($ticket === null) {
            throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
        }
        $view = $this->listItem($ticket);
        $view['messages'] = array_map(function (array $message): array {
            return [
                'uuid' => $message['uuid'],
                'role' => $message['role'],
                'author' => $message['author'],
                'body' => $message['body'],
                'createdAt' => $this->iso((string) $message['created_at']),
                'attachments' => $message['attachments'] ?? [],
            ];
        }, $ticket['messages'] ?? []);

        return $view;
    }

    /** @param array<string, mixed> $ticket */
    private function listItem(array $ticket): array
    {
        $category = Category::from((string) $ticket['category']);
        $status = TicketStatus::from((string) $ticket['status']);

        return [
            'uuid' => $ticket['uuid'],
            'category' => $category->value,
            'categoryLabel' => $category->label(),
            'subject' => $ticket['subject'],
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'tenantName' => $ticket['tenant_name'],
            'tenantSlug' => $ticket['tenant_slug'],
            'authorName' => $ticket['author_name'],
            'authorEmail' => $ticket['author_email'],
            'createdAt' => $this->iso((string) $ticket['created_at']),
            'updatedAt' => $this->iso((string) $ticket['updated_at']),
            'preview' => mb_substr(trim((string) ($ticket['last_body'] ?? '')), 0, 160),
            'attachmentCount' => (int) ($ticket['attachment_count'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $row */
    private function file(array $row): array
    {
        return [
            'path' => $this->files->path((string) $row['storage_key']),
            'name' => (string) $row['original_name'],
            'mime' => (string) $row['mime_type'],
        ];
    }

    /** @param list<array{storage_key: string, original_name: string, mime_type: string, size_bytes: int}> $stored
     * @return list<array<string, mixed>>
     */
    private function attachmentRows(string $ticketUuid, string $messageUuid, array $stored, string $now): array
    {
        return array_map(static fn (array $file): array => [
            'uuid' => Uuid::uuid4()->toString(),
            'ticket_uuid' => $ticketUuid,
            'message_uuid' => $messageUuid,
            'original_name' => $file['original_name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'storage_key' => $file['storage_key'],
            'created_at' => $now,
        ], $stored);
    }

    /** @return array<string, mixed> */
    private function messageRow(string $uuid, string $ticketUuid, string $role, string $authorUuid, string $label, string $body, string $now): array
    {
        return [
            'uuid' => $uuid,
            'ticket_uuid' => $ticketUuid,
            'author_role' => $role,
            'author_uuid' => $authorUuid,
            'author_label' => mb_substr($label, 0, 200),
            'body' => $body,
            'created_at' => $now,
        ];
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function iso(string $value): string
    {
        return str_replace(' ', 'T', $value).'Z';
    }
}
