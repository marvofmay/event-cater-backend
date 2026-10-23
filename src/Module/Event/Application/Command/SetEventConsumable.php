<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

use App\Common\Domain\ValueObject\Quantity;

final readonly class SetEventConsumable
{
    public function __construct(public string $uuid, public int $version, public string $itemUuid, public Quantity $quantity, public string $actorUuid)
    {
    }
}
