<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Command;

final readonly class CreateEquipmentItem
{
    public function __construct(public string $uuid, public string $code, public string $name, public ?string $category, public string $unit, public ?string $description, public ?string $categoryUuid = null, public ?string $slangName = null, public int $stockQuantity = 0, public ?string $storageLocationUuid = null)
    {
    }
}
