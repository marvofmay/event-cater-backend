<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

use App\Common\Domain\ValueObject\Quantity;

final readonly class AmendEventPreparation
{
    public function __construct(
        public string $uuid,
        public int $version,
        public string $action,
        public string $kind,
        public string $itemUuid,
        public string $comment,
        public string $actorUuid,
        public ?Quantity $requiredQuantity = null,
        public ?int $equipmentQuantity = null,
    ) {
    }
}
