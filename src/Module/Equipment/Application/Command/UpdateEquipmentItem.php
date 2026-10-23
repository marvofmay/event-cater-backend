<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Command;

final readonly class UpdateEquipmentItem
{
    public function __construct(public string $uuid, public int $version, public string $code, public string $name, public string $category, public string $unit, public ?string $description)
    {
    }
}
