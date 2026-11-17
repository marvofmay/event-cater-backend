<?php

declare(strict_types=1);

namespace App\Module\Support\Infrastructure;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Saas\Infrastructure\ControlPlane;
use App\Module\Support\Application\Port\TicketStore;
use App\Module\Support\Domain\TicketStatus;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final readonly class DbalTicketStore implements TicketStore
{
    public function __construct(private ControlPlane $control)
    {
    }

    public function countRecent(string $tenantUuid, string $authorUuid, string $since): int
    {
        return (int) $this->call(fn (): mixed => $this->control->db()->fetchOne(
            'SELECT COUNT(*) FROM support_ticket WHERE tenant_uuid=? AND author_uuid=? AND created_at>=?',
            [$tenantUuid, $authorUuid, $since],
        ));
    }

    public function add(array $ticket, array $message, array $attachments): void
    {
        $this->call(function () use ($ticket, $message, $attachments): void {
            $this->control->db()->transactional(function () use ($ticket, $message, $attachments): void {
                $db = $this->control->db();
                $db->insert('support_ticket', $ticket);
                $db->insert('support_message', $message);
                foreach ($attachments as $attachment) {
                    $db->insert('support_attachment', $attachment);
                }
            });
        });
    }

    public function findForAuthor(string $tenantUuid, string $authorUuid, string $ticketUuid): ?array
    {
        $row = $this->call(fn (): array|false => $this->control->db()->fetchAssociative(
            'SELECT * FROM support_ticket WHERE uuid=? AND tenant_uuid=? AND author_uuid=?',
            [$ticketUuid, $tenantUuid, $authorUuid],
        ));

        return $this->hydrate($row ?: null);
    }

    public function listForAuthor(string $tenantUuid, string $authorUuid): array
    {
        $rows = $this->call(fn (): array => $this->control->db()->fetchAllAssociative(
            'SELECT t.*, '.$this->listColumns().' FROM support_ticket t WHERE t.tenant_uuid=? AND t.author_uuid=? ORDER BY t.updated_at DESC LIMIT 100',
            [$tenantUuid, $authorUuid],
        ));

        return $rows;
    }

    public function find(string $ticketUuid): ?array
    {
        $row = $this->call(fn (): array|false => $this->control->db()->fetchAssociative('SELECT * FROM support_ticket WHERE uuid=?', [$ticketUuid]));

        return $this->hydrate($row ?: null);
    }

    public function listForOperator(?string $status, ?string $category, ?string $search): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status !== null) {
            $where[] = 't.status=?';
            $params[] = $status;
        }
        if ($category !== null) {
            $where[] = 't.category=?';
            $params[] = $category;
        }
        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $where[] = '(t.subject LIKE ? OR t.author_name LIKE ? OR t.author_email LIKE ? OR t.tenant_name LIKE ? OR t.tenant_slug LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return $this->call(fn (): array => $this->control->db()->fetchAllAssociative(
            'SELECT t.*, '.$this->listColumns().' FROM support_ticket t WHERE '.implode(' AND ', $where).' ORDER BY t.updated_at DESC LIMIT 200',
            $params,
        ));
    }

    public function summary(): array
    {
        $counts = array_fill_keys(array_map(static fn (TicketStatus $status): string => $status->value, TicketStatus::cases()), 0);
        $rows = $this->call(fn (): array => $this->control->db()->fetchAllAssociative('SELECT status, COUNT(*) AS total FROM support_ticket GROUP BY status'));
        foreach ($rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function addMessage(string $ticketUuid, array $message, string $status, string $updatedAt): void
    {
        $this->call(function () use ($ticketUuid, $message, $status, $updatedAt): void {
            $this->control->db()->transactional(function () use ($ticketUuid, $message, $status, $updatedAt): void {
                $db = $this->control->db();
                $db->insert('support_message', $message);
                $db->update('support_ticket', ['status' => $status, 'updated_at' => $updatedAt], ['uuid' => $ticketUuid]);
                if (!$db->fetchOne('SELECT 1 FROM support_ticket WHERE uuid=?', [$ticketUuid])) {
                    throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
                }
            });
        });
    }

    public function updateStatus(string $ticketUuid, string $status, string $updatedAt): void
    {
        $this->call(function () use ($ticketUuid, $status, $updatedAt): void {
            $db = $this->control->db();
            $db->update('support_ticket', ['status' => $status, 'updated_at' => $updatedAt], ['uuid' => $ticketUuid]);
            if (!$db->fetchOne('SELECT 1 FROM support_ticket WHERE uuid=?', [$ticketUuid])) {
                throw new PlanningResourceNotFound('Nie znaleziono zgłoszenia.');
            }
        });
    }

    public function attachment(string $ticketUuid, string $attachmentUuid): ?array
    {
        $row = $this->call(fn (): array|false => $this->control->db()->fetchAssociative(
            'SELECT a.storage_key, a.original_name, a.mime_type, t.tenant_uuid, t.author_uuid
             FROM support_attachment a JOIN support_ticket t ON t.uuid=a.ticket_uuid
             WHERE a.uuid=? AND a.ticket_uuid=?',
            [$attachmentUuid, $ticketUuid],
        ));

        return $row ?: null;
    }

    private function listColumns(): string
    {
        return '(SELECT body FROM support_message m WHERE m.ticket_uuid=t.uuid ORDER BY created_at DESC, uuid DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM support_attachment a WHERE a.ticket_uuid=t.uuid) AS attachment_count';
    }

    /** @param array<string, mixed>|null $ticket
     * @return array<string, mixed>|null
     */
    private function hydrate(?array $ticket): ?array
    {
        if ($ticket === null) {
            return null;
        }
        $messages = $this->control->db()->fetchAllAssociative(
            'SELECT uuid, author_role, author_label, body, created_at FROM support_message WHERE ticket_uuid=? ORDER BY created_at ASC, uuid ASC',
            [$ticket['uuid']],
        );
        $attachments = $this->control->db()->fetchAllAssociative(
            'SELECT uuid, message_uuid, original_name, mime_type, size_bytes FROM support_attachment WHERE ticket_uuid=? ORDER BY created_at ASC',
            [$ticket['uuid']],
        );
        $byMessage = [];
        foreach ($attachments as $attachment) {
            $byMessage[$attachment['message_uuid']][] = [
                'uuid' => $attachment['uuid'],
                'name' => $attachment['original_name'],
                'mime' => $attachment['mime_type'],
                'size' => (int) $attachment['size_bytes'],
            ];
        }
        $ticket['messages'] = array_map(static fn (array $message): array => [
            'uuid' => $message['uuid'],
            'role' => $message['author_role'],
            'author' => $message['author_label'],
            'body' => $message['body'],
            'created_at' => $message['created_at'],
            'attachments' => $byMessage[$message['uuid']] ?? [],
        ], $messages);
        $ticket['attachment_count'] = count($attachments);
        $ticket['last_body'] = $messages === [] ? '' : (string) $messages[array_key_last($messages)]['body'];

        return $ticket;
    }

    private function call(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (TableNotFoundException $error) {
            throw new ServiceUnavailableHttpException(null, 'Moduł zgłoszeń nie jest gotowy. Uruchom migrację centralnej bazy.', $error);
        }
    }
}
