<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class GetEventMedia
{
    public function __construct(public string $eventUuid, public string $attachmentUuid)
    {
    }
}
