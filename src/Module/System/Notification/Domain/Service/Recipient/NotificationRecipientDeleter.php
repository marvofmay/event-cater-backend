<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Service\Recipient;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientDeleterInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientWriterInterface;

final readonly class NotificationRecipientDeleter implements NotificationRecipientDeleterInterface
{
    public function __construct(private NotificationRecipientWriterInterface $notificationRecipientWriterRepository)
    {
    }

    public function delete(NotificationRecipient $notificationRecipient): void
    {
        $this->notificationRecipientWriterRepository->delete($notificationRecipient);
    }
}
