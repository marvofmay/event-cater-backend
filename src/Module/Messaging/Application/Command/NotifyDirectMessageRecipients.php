<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Command;

final readonly class NotifyDirectMessageRecipients
{
    /**
     * @param list<string> $recipientUuids
     */
    public function __construct(
        public string $messageUuid,
        public string $title,
        public string $body,
        public array $recipientUuids,
    ) {
    }
}
