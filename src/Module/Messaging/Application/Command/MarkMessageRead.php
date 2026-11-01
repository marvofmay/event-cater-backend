<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Command;

final readonly class MarkMessageRead
{
    public function __construct(public string $uuid, public string $userUuid)
    {
    }
}
