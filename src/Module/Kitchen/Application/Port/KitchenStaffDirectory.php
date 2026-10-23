<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Port;

interface KitchenStaffDirectory
{
    public function search(string $query): array;
    public function requireActive(string $uuid): string;
}
