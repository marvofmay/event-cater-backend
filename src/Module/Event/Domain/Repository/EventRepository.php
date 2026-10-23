<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Repository;

use App\Module\Event\Domain\Entity\CateringEvent;

interface EventRepository
{
    public function add(CateringEvent $event): void;
    public function getForUpdate(string $uuid, int $expectedVersion): CateringEvent;
}
