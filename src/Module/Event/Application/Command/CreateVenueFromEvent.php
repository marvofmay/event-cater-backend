<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class CreateVenueFromEvent
{
    public function __construct(public string $uuid, public int $version, public string $venueUuid, public string $name, public ?string $notes, public string $serviceMode)
    {
    }
}
