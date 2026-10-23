<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Repository;

use App\Module\Event\Domain\Entity\EventVenue;

interface VenueRepository
{
    public function add(EventVenue $venue): void;
    public function get(string $uuid, ?int $version = null, bool $exclusive = false): EventVenue;
}
