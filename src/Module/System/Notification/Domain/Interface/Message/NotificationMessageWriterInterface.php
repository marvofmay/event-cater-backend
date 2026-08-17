<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Message;

use App\Module\System\Notification\Domain\Entity\NotificationMessage;

interface NotificationMessageWriterInterface
{
    public function save(NotificationMessage $notificationMessage): void;
    public function delete(NotificationMessage $notificationMessage): void;
}
