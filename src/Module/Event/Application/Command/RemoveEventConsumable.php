<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class RemoveEventConsumable
{
    public function __construct(public string $uuid, public int $version, public string $itemUuid, public string $actorUuid)
    {
    }
}
