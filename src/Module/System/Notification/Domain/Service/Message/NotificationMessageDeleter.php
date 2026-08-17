<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Service\Message;

use App\Module\System\Notification\Domain\Entity\NotificationMessage;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageDeleterInterface;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageWriterInterface;

final readonly class NotificationMessageDeleter implements NotificationMessageDeleterInterface
{
    public function __construct(private NotificationMessageWriterInterface $notificationMessageWriterRepository)
    {
    }

    public function delete(NotificationMessage $notificationMessage): void
    {
        $this->notificationMessageWriterRepository->delete($notificationMessage);
    }
}
