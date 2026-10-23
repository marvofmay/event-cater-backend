<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

use App\Common\Domain\ValueObject\Quantity;

final readonly class RecordPreparationProgress
{
    public function __construct(public string $uuid, public int $version, public string $lineUuid, public ?Quantity $prepared, public ?Quantity $loaded, public ?string $correctionReason, public string $actorUuid)
    {
    }
}
