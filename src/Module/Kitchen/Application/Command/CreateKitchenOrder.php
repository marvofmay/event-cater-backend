<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Command;

final readonly class CreateKitchenOrder
{
    public function __construct(public string $uuid, public string $eventUuid, public int $eventVersion, public ?string $productionDate, public ?\DateTimeImmutable $dueAt, public ?string $responsibleUuid, public ?string $notes, public string $actorUuid, public string $serviceMode = 'off_site')
    {
    }
}
