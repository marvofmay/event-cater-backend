<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Command;

final readonly class SendMessage
{
    public function __construct(public string $uuid, public string $senderUuid, public string $title, public string $content, public array $recipientUuids, public ?string $replyToUuid = null)
    {
    }
}
