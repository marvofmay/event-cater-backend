<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Port;

interface EquipmentCatalog
{
    /** @return array{uuid: string, name: string, code: string, unit: string} */
    public function requireActive(string $uuid): array;
}
