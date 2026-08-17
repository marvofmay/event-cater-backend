<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Recipient;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;

interface NotificationRecipientWriterInterface
{
    public function save(NotificationRecipient $notificationRecipient): void;
    public function delete(NotificationRecipient $notificationRecipient): void;
}
