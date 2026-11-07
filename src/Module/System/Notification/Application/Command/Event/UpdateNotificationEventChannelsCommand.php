<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\Command\Event;

final readonly class UpdateNotificationEventChannelsCommand
{
    /**
     * @param array<string, bool> $channels
     */
    public function __construct(
        public string $eventName,
        public array $channels,
    ) {
    }
}
