<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ConfigurePreparationLine
{
    public function __construct(public string $uuid, public int $version, public string $lineUuid, public ?string $responsibleUuid, public ?string $sourceLocation, public ?string $instructions, public string $actorUuid)
    {
    }
}
