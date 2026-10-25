<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Command;

final readonly class AddEquipmentPhoto
{
    public function __construct(public string $uuid, public int $version, public string $name, public string $storedName, public string $mimeType, public int $size)
    {
    }
}
