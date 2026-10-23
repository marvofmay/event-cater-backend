<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ConfigureEventPreparation
{
    public function __construct(public string $uuid, public int $version, public ?string $responsibleUuid, public ?\DateTimeImmutable $deadlineAt, public ?string $notes, public string $actorUuid)
    {
    }
}
