<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Command;

final readonly class UpdateKitchenDraft
{
    public function __construct(public string $uuid, public int $version, public int $eventVersion, public \App\Module\Kitchen\Domain\ValueObject\KitchenPlan $plan, public string $actorUuid)
    {
    }
}
