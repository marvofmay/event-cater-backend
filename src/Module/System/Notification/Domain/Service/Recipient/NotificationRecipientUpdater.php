<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Service\Recipient;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientUpdaterInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientWriterInterface;

final readonly class NotificationRecipientUpdater implements NotificationRecipientUpdaterInterface
{
    public function __construct(private NotificationRecipientWriterInterface $notificationRecipientWriter)
    {
    }

    public function markAsRead(NotificationRecipient $notificationRecipient): void
    {
        $notificationRecipient->markAsRead();
        $this->notificationRecipientWriter->save($notificationRecipient);
    }
}
