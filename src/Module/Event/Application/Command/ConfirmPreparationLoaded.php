<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ConfirmPreparationLoaded
{
    public function __construct(public string $uuid, public int $version, public ?string $acceptShortagesReason, public string $actorUuid)
    {
    }
}
