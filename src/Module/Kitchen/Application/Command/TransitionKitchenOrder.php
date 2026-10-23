<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Command;

final readonly class TransitionKitchenOrder
{
    public function __construct(public string $uuid, public int $version, public string $action, public ?string $reason, public string $actorUuid)
    {
    }
}
