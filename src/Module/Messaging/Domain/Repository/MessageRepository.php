<?php

declare(strict_types=1);

namespace App\Module\Messaging\Domain\Repository;

use App\Module\Messaging\Domain\Entity\DirectMessage;

interface MessageRepository
{
    public function find(string $uuid): ?DirectMessage;
    public function add(DirectMessage $message): void;
    public function markRead(string $messageUuid, string $userUuid): void;
    public function replyRecipient(string $messageUuid, string $userUuid): string;
    public function hide(array $uuids, string $userUuid, string $folder): void;
}
