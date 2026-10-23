<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

use App\Module\Event\Application\DTO\EventDetails;

final readonly class CreateEvent
{
    public function __construct(public string $uuid, public EventDetails $details, public string $actorUuid)
    {
    }
}
