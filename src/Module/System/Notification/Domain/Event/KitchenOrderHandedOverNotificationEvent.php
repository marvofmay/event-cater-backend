<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Event;

use App\Module\System\Notification\Domain\Interface\Event\NotificationEventInterface;

final class KitchenOrderHandedOverNotificationEvent implements NotificationEventInterface
{
    public function getName(): string
    {
        return 'KitchenOrderHandedOver';
    }

    public function getLabel(): string
    {
        return 'notification.event.KitchenOrderHandedOver';
    }
}
