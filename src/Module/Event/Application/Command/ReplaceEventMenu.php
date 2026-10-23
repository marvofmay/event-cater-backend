<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class ReplaceEventMenu
{
    public function __construct(public string $uuid, public int $version, public array $items, public string $actorUuid)
    {
    }
}
