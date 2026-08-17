<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Message;

use App\Module\System\Notification\Domain\Entity\NotificationMessage;

interface NotificationMessageDeleterInterface
{
    public function delete(NotificationMessage $notificationMessage): void;
}
