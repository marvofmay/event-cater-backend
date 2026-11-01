<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Query;

final readonly class GetMessage
{
    public function __construct(public string $uuid, public string $userUuid)
    {
    }
}
