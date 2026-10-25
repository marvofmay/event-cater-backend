<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Repository;

use App\Module\Equipment\Domain\Entity\EquipmentItem;

interface EquipmentRepository
{
    public function add(EquipmentItem $item): void;
    public function remove(EquipmentItem $item): void;
    public function getForUpdate(string $uuid, int $expectedVersion): EquipmentItem;
    public function assertCodeAvailable(string $code, string $exceptUuid): void;
}
