<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ApplyPreparationTemplate
{
    public function __construct(public string $uuid, public int $version, public string $templateUuid, public int $templateVersion, public string $actorUuid)
    {
    }
}
