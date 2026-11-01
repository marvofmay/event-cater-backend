<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Port;

interface RecipientDirectory
{
    public function search(string $phrase): array;
    public function resolve(array $uuids): array;
    public function senderName(string $uuid): string;
}
