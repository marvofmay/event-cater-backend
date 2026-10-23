<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class RemoveEventStaff
{
    public function __construct(public string $uuid, public int $version, public string $employeeUuid, public string $actorUuid)
    {
    }
}
