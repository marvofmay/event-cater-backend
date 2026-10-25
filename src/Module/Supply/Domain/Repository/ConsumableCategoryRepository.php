<?php

declare(strict_types=1);

namespace App\Module\Supply\Domain\Repository;

use App\Module\Supply\Domain\Entity\ConsumableCategory;

interface ConsumableCategoryRepository
{
    public function add(ConsumableCategory $category): void;
    public function getForUpdate(string $uuid, ?int $version = null): ConsumableCategory;
    public function findByName(string $name): ?ConsumableCategory;
    public function assertNameAvailable(string $name, string $exceptUuid): void;
    public function remove(ConsumableCategory $category): void;
}
