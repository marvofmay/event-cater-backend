<?php

declare(strict_types=1);

namespace App\Module\Support\Application\Port;

interface TicketStore
{
    public function countRecent(string $tenantUuid, string $authorUuid, string $since): int;

    /** @param array<string, mixed> $ticket @param array<string, mixed> $message @param list<array<string, mixed>> $attachments */
    public function add(array $ticket, array $message, array $attachments): void;

    /** @return array<string, mixed>|null */
    public function findForAuthor(string $tenantUuid, string $authorUuid, string $ticketUuid): ?array;

    /** @return list<array<string, mixed>> */
    public function listForAuthor(string $tenantUuid, string $authorUuid): array;

    /** @return array<string, mixed>|null */
    public function find(string $ticketUuid): ?array;

    /** @return list<array<string, mixed>> */
    public function listForOperator(?string $status, ?string $category, ?string $search, ?string $tenantSlug = null): array;

    /** @return array<string, int> */
    public function summary(): array;

    /** Latest message time, so the next reply can stay after it even in the same second. */
    public function latestMessageAt(string $ticketUuid): ?string;

    /** @param array<string, mixed> $message */
    public function addMessage(string $ticketUuid, array $message, string $status, string $updatedAt): void;

    public function updateStatus(string $ticketUuid, string $status, string $updatedAt): void;

    /** @return array<string, mixed>|null */
    public function attachment(string $ticketUuid, string $attachmentUuid): ?array;
}
