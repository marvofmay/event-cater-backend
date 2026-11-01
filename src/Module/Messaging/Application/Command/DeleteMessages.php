<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Command;

final readonly class DeleteMessages
{
    public function __construct(public array $uuids, public string $userUuid, public string $folder)
    {
    }
}
