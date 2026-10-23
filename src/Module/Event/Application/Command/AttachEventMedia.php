<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class AttachEventMedia
{
    public function __construct(public string $uuid, public int $version, public string $fileName, public string $storedName, public string $extension, public string $mimeType, public int $size, public ?string $description, public string $actorUuid)
    {
    }
}
