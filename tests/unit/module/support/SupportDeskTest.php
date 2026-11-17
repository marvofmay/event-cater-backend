<?php

declare(strict_types=1);

namespace App\tests\unit\module\support;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Support\Application\Command\ChangeTicketStatus;
use App\Module\Support\Application\Command\OpenTicket;
use App\Module\Support\Application\Command\ReplyToTicket;
use App\Module\Support\Application\Port\AttachmentStorage;
use App\Module\Support\Application\Port\TicketStore;
use App\Module\Support\Application\Reporter;
use App\Module\Support\Application\SupportDesk;
use App\Module\Support\Domain\TicketPolicy;
use App\Module\Support\Domain\TicketStatus;
use App\Module\Support\Infrastructure\LocalAttachmentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SupportDeskTest extends TestCase
{
    public function testOpeningRequiresCategorySubjectAndEitherMessageOrImage(): void
    {
        $files = new MemoryFiles();
        $desk = new SupportDesk(new MemoryTickets(), $files);
        $reporter = $this->reporter();

        try {
            $desk->open(new OpenTicket($reporter, 'not-a-category', 'Temat zgłoszenia', 'Treść', []));
            self::fail('Kategoria powinna być wymagana.');
        } catch (BusinessRuleViolation) {
        }
        try {
            $desk->open(new OpenTicket($reporter, 'billing', 'Temat zgłoszenia', '', []));
            self::fail('Pusta wiadomość bez załącznika powinna zostać odrzucona.');
        } catch (BusinessRuleViolation) {
        }
        self::assertSame(0, $files->accepted);

        $uuid = $desk->open(new OpenTicket($reporter, 'billing', 'Faktura za abonament', '', [new UploadedFile($this->tempFile(), 'a.png', test: true)]));
        self::assertNotSame('', $uuid);
        self::assertSame(1, $files->accepted);
    }

    public function testUserReplyReopensAWaitingTicketAndAnotherAuthorCannotReadIt(): void
    {
        $store = new MemoryTickets();
        $desk = new SupportDesk($store, new MemoryFiles());
        $author = $this->reporter();
        $uuid = $desk->open(new OpenTicket($author, 'system_error', 'Pusty ekran po zapisie', 'Zostaje biała strona.', []));
        $desk->changeStatus(new ChangeTicketStatus($uuid, TicketStatus::Waiting->value));

        $reopened = $desk->replyAsUser($author, $uuid, 'Nadal się powtarza.');
        self::assertSame(TicketStatus::Open->value, $reopened['status']);
        self::assertSame('Zespół EventCater', $desk->replyAsOperator(new ReplyToTicket($uuid, 'Sprawdzamy logi.', 'operator-1', 'Zespół EventCater', 'operator', status: TicketStatus::InProgress->value))['messages'][2]['author']);

        $this->expectException(PlanningResourceNotFound::class);
        $desk->getMine($this->reporter('00000000-0000-4000-8000-000000000099'), $uuid);
    }

    public function testHourlyLimitRejectsBeforeStoringFiles(): void
    {
        $files = new MemoryFiles();
        $store = new MemoryTickets();
        $desk = new SupportDesk($store, $files);
        $author = $this->reporter();
        for ($i = 0; $i < TicketPolicy::HOURLY_LIMIT; ++$i) {
            $desk->open(new OpenTicket($author, 'question', 'Pytanie numer '.$i, 'Treść pytania '.$i, []));
        }

        try {
            $desk->open(new OpenTicket($author, 'question', 'Jeszcze jedno pytanie', 'To już ponad limit.', []));
            self::fail('Limit godzinny powinien zatrzymać kolejne zgłoszenie.');
        } catch (BusinessRuleViolation) {
            self::assertSame(TicketPolicy::HOURLY_LIMIT, $files->accepted);
        }
    }

    public function testRejectedImageIsNotKeptAndARealPngIsStored(): void
    {
        $directory = sys_get_temp_dir().'/support-storage-'.bin2hex(random_bytes(4));
        $storage = new LocalAttachmentStorage($directory);
        $text = tempnam(sys_get_temp_dir(), 'support-text');
        $png = tempnam(sys_get_temp_dir(), 'support-png');
        $webp = tempnam(sys_get_temp_dir(), 'support-webp');
        file_put_contents($text, 'to nie jest obraz');
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $chunk = 'VP8X'.pack('V', 10)."\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        file_put_contents($webp, 'RIFF'.pack('V', 4 + strlen($chunk)).'WEBP'.$chunk);
        try {
            try {
                $storage->accept([new UploadedFile($text, 'notatka.png', test: true)]);
                self::fail('Plik tekstowy z rozszerzeniem PNG powinien zostać odrzucony.');
            } catch (BusinessRuleViolation) {
                self::assertSame([], $this->files($directory));
            }
            $stored = $storage->accept([
                new UploadedFile($png, 'Zrzut ekranu.png', test: true),
                new UploadedFile($webp, 'widok.webp', test: true),
            ]);
            self::assertCount(2, $stored);
            self::assertSame('image/png', $stored[0]['mime_type']);
            self::assertSame('image/webp', $stored[1]['mime_type']);
            self::assertCount(2, $this->files($directory));
            $storage->discard(array_column($stored, 'storage_key'));
            self::assertSame([], $this->files($directory));
        } finally {
            @rmdir($directory);
            foreach ([$text, $png, $webp] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testFailedSaveRemovesAlreadyStoredImages(): void
    {
        $files = new MemoryFiles();
        $store = new MemoryTickets();
        $store->fail = true;
        $desk = new SupportDesk($store, $files);
        $path = tempnam(sys_get_temp_dir(), 'support-fail');
        file_put_contents($path, 'x');
        try {
            $desk->open(new OpenTicket($this->reporter(), 'other', 'Temat zgłoszenia', '', [new UploadedFile($path, 'a.png', test: true)]));
            self::fail('Zapis powinien się nie udać.');
        } catch (\RuntimeException) {
            self::assertSame(['10000000-0000-4000-8000-000000000001'], $files->discarded);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return list<string> */
    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_values(array_filter(scandir($directory) ?: [], static fn (string $name): bool => $name !== '.' && $name !== '..'));
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'support-memory');
        file_put_contents($path, 'x');

        return $path;
    }

    private function reporter(string $user = '00000000-0000-4000-8000-000000000001'): Reporter
    {
        return new Reporter($user, 'anna@example.test', 'Anna Nowak', '00000000-0000-4000-8000-000000000010', 'mapa-smakow', 'Mapa Smaków');
    }
}

final class MemoryFiles implements AttachmentStorage
{
    public int $accepted = 0;
    /** @var list<string> */
    public array $discarded = [];

    public function accept(array $files): array
    {
        TicketPolicy::assertAttachmentCount(count($files));
        ++$this->accepted;
        $stored = [];
        foreach ($files as $index => $file) {
            $stored[] = [
                'storage_key' => sprintf('10000000-0000-4000-8000-%012d', $index + 1),
                'original_name' => 'zrzut.png',
                'mime_type' => 'image/png',
                'size_bytes' => 4,
            ];
        }

        return $stored;
    }

    public function discard(array $keys): void
    {
        $this->discarded = array_values($keys);
    }

    public function path(string $key): string
    {
        return $key;
    }
}

final class MemoryTickets implements TicketStore
{
    /** @var array<string, array<string, mixed>> */
    public array $tickets = [];
    /** @var list<array<string, mixed>> */
    public array $messages = [];
    /** @var list<array<string, mixed>> */
    public array $attachments = [];
    public bool $fail = false;

    public function countRecent(string $tenantUuid, string $authorUuid, string $since): int
    {
        return count(array_filter($this->tickets, static fn (array $ticket): bool => $ticket['tenant_uuid'] === $tenantUuid && $ticket['author_uuid'] === $authorUuid && $ticket['created_at'] >= $since));
    }

    public function add(array $ticket, array $message, array $attachments): void
    {
        if ($this->fail) {
            throw new \RuntimeException('db down');
        }
        $this->tickets[$ticket['uuid']] = $ticket;
        $this->messages[] = $message;
        foreach ($attachments as $attachment) {
            $this->attachments[] = $attachment;
        }
    }

    public function findForAuthor(string $tenantUuid, string $authorUuid, string $ticketUuid): ?array
    {
        $ticket = $this->tickets[$ticketUuid] ?? null;
        if ($ticket === null || $ticket['tenant_uuid'] !== $tenantUuid || $ticket['author_uuid'] !== $authorUuid) {
            return null;
        }

        return $this->hydrate($ticket);
    }

    public function listForAuthor(string $tenantUuid, string $authorUuid): array
    {
        return array_values(array_map($this->listRow(...), array_filter(
            $this->tickets,
            static fn (array $ticket): bool => $ticket['tenant_uuid'] === $tenantUuid && $ticket['author_uuid'] === $authorUuid,
        )));
    }

    public function find(string $ticketUuid): ?array
    {
        return isset($this->tickets[$ticketUuid]) ? $this->hydrate($this->tickets[$ticketUuid]) : null;
    }

    public function listForOperator(?string $status, ?string $category, ?string $search): array
    {
        return array_values(array_map($this->listRow(...), $this->tickets));
    }

    public function summary(): array
    {
        $counts = [];
        foreach ($this->tickets as $ticket) {
            $counts[$ticket['status']] = ($counts[$ticket['status']] ?? 0) + 1;
        }

        return $counts;
    }

    public function addMessage(string $ticketUuid, array $message, string $status, string $updatedAt): void
    {
        $this->messages[] = $message;
        $this->tickets[$ticketUuid]['status'] = $status;
        $this->tickets[$ticketUuid]['updated_at'] = $updatedAt;
    }

    public function updateStatus(string $ticketUuid, string $status, string $updatedAt): void
    {
        $this->tickets[$ticketUuid]['status'] = $status;
        $this->tickets[$ticketUuid]['updated_at'] = $updatedAt;
    }

    public function attachment(string $ticketUuid, string $attachmentUuid): ?array
    {
        return null;
    }

    /** @param array<string, mixed> $ticket
     * @return array<string, mixed>
     */
    private function hydrate(array $ticket): array
    {
        $messages = array_values(array_filter($this->messages, static fn (array $message): bool => $message['ticket_uuid'] === $ticket['uuid']));
        $ticket['messages'] = array_map(fn (array $message): array => [
            'uuid' => $message['uuid'],
            'role' => $message['author_role'],
            'author' => $message['author_label'],
            'body' => $message['body'],
            'created_at' => $message['created_at'],
            'attachments' => [],
        ], $messages);
        $ticket['last_body'] = $messages === [] ? '' : (string) $messages[array_key_last($messages)]['body'];
        $ticket['attachment_count'] = 0;

        return $ticket;
    }

    /** @param array<string, mixed> $ticket
     * @return array<string, mixed>
     */
    private function listRow(array $ticket): array
    {
        return $this->hydrate($ticket);
    }
}
