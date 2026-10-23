<?php

declare(strict_types=1);

namespace App\Module\Supply\Domain\Repository;

use App\Module\Supply\Domain\Entity\ConsumableItem;

interface ConsumableRepository
{
    public function add(ConsumableItem $item): void;
    public function getForUpdate(string $uuid, int $version): ConsumableItem;
}
