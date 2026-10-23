<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

use App\Module\Event\Domain\Enum\EventStatus;

final readonly class ChangeEventStatus
{
    public function __construct(public string $uuid, public int $version, public EventStatus $status, public string $actorUuid)
    {
    }
}
