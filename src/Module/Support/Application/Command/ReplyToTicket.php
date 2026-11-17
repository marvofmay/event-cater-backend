<?php

declare(strict_types=1);

namespace App\Module\Support\Application\Command;

final readonly class ReplyToTicket
{
    public function __construct(
        public string $ticketUuid,
        public string $message,
        public string $authorUuid,
        public string $authorLabel,
        public string $role,
        public ?string $tenantUuid = null,
        public ?string $authorUserUuid = null,
        public ?string $status = null,
    ) {
    }
}
