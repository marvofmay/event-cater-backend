<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Command;

final readonly class RecordKitchenProgress
{
    public function __construct(public string $uuid, public int $version, public string $lineUuid, public ?int $preparedPortions, public ?int $handedOverPortions, public ?string $correctionReason, public string $actorUuid)
    {
    }
}
