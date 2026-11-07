<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Event;

use App\Module\System\Notification\Domain\Interface\Event\NotificationEventInterface;

final class KitchenDueReminderNotificationEvent implements NotificationEventInterface
{
    public function getName(): string
    {
        return 'KitchenDueReminder';
    }

    public function getLabel(): string
    {
        return 'notification.event.KitchenDueReminder';
    }
}
