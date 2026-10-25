<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Query;

final readonly class GetEquipmentLocation
{
    public function __construct(public string $uuid)
    {
    }
}
