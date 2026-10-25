<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Command;

final readonly class SaveEquipmentLocation
{
    public function __construct(public string $uuid, public string $name, public ?int $version = null)
    {
    }
}
