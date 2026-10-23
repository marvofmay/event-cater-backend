<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Repository;

use App\Module\Equipment\Domain\Entity\EquipmentCategory;

interface EquipmentCategoryRepository
{
    public function add(EquipmentCategory $category): void;
    public function getForUpdate(string $uuid, ?int $version = null): EquipmentCategory;
    public function findByName(string $name): ?EquipmentCategory;
    public function assertNameAvailable(string $name, string $exceptUuid): void;
    public function remove(EquipmentCategory $category): void;
}
