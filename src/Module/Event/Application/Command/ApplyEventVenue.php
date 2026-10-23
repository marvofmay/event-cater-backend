<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ApplyEventVenue
{
    public function __construct(public string $uuid, public int $version, public string $venueUuid, public int $venueVersion, public bool $replaceContacts, public string $actorUuid)
    {
    }
}
