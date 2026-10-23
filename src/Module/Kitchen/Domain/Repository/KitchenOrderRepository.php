<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\Repository;

use App\Module\Kitchen\Domain\Entity\KitchenOrder;

interface KitchenOrderRepository
{
    public function add(KitchenOrder $order): void;
    public function eventUuid(string $uuid): string;
    public function getForUpdate(string $uuid, int $version): KitchenOrder;
}
