<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Port;

interface EmployeeDirectory
{
    /** Locks the employee for the command transaction; returns their display name. */
    public function requireActive(string $uuid, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string;
}
