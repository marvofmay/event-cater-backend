<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class SetEventEquipment
{
    public function __construct(public string $uuid, public int $version, public string $equipmentUuid, public int $quantity, public ?string $notes, public string $actorUuid)
    {
    }
}
