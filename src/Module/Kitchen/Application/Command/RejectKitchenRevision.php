<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Command;

final readonly class RejectKitchenRevision
{
    public function __construct(public string $uuid, public int $version, public string $reason, public string $actorUuid)
    {
    }
}
