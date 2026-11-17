<?php

declare(strict_types=1);

namespace App\Module\Support\Application\Command;

final readonly class ChangeTicketStatus
{
    public function __construct(public string $ticketUuid, public string $status)
    {
    }
}
