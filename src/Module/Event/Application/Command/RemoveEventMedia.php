<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class RemoveEventMedia
{
    public function __construct(public string $uuid, public int $version, public string $attachmentUuid, public string $actorUuid)
    {
    }
}
