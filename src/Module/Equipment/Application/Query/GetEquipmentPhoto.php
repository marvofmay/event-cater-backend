<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Query;

final readonly class GetEquipmentPhoto
{
    public function __construct(public string $equipmentUuid, public string $photoUuid)
    {
    }
}
