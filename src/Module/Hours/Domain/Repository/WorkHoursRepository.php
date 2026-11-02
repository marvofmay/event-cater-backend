<?php

declare(strict_types=1);

namespace App\Module\Hours\Domain\Repository;

use App\Module\Hours\Domain\Entity\WorkHoursEntry;

interface WorkHoursRepository
{
    public function find(string $uuid): ?WorkHoursEntry;

    public function add(WorkHoursEntry $entry): void;

    public function remove(WorkHoursEntry $entry): void;
}
