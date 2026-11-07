<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Event;

use App\Module\System\Notification\Domain\Interface\Event\NotificationEventInterface;

final class EventStatusChangedNotificationEvent implements NotificationEventInterface
{
    public function getName(): string
    {
        return 'EventStatusChanged';
    }

    public function getLabel(): string
    {
        return 'notification.event.EventStatusChanged';
    }
}
