<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ReplaceEventContacts
{
    public function __construct(public string $uuid, public int $version, public array $contacts, public string $actorUuid)
    {
    }
}
