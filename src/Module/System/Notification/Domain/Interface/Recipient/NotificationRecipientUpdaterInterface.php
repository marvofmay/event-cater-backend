<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Recipient;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;

interface NotificationRecipientUpdaterInterface
{
    public function markAsRead(NotificationRecipient $notificationRecipient): void;
}
