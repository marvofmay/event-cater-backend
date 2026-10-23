<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Command;

final readonly class ChangeEquipmentActive
{
    public function __construct(public string $uuid, public int $version, public bool $active)
    {
    }
}
