<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Repository;

use App\Module\Equipment\Domain\Entity\EquipmentLocation;

interface EquipmentLocationRepository
{
    public function add(EquipmentLocation $category): void;
    public function getForUpdate(string $uuid, ?int $version = null): EquipmentLocation;
    public function findByName(string $name): ?EquipmentLocation;
    public function assertNameAvailable(string $name, string $exceptUuid): void;
    public function remove(EquipmentLocation $category): void;
}
