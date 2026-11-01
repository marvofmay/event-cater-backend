<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Service\Message;

use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Entity\NotificationMessage;
use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Entity\NotificationTemplateSetting;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageCreatorInterface;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageWriterInterface;

final readonly class NotificationMessageCreator implements NotificationMessageCreatorInterface
{
    public function __construct(private NotificationMessageWriterInterface $notificationMessageWriter)
    {
    }

    public function create(
        NotificationEventSetting $event,
        NotificationChannelSetting $channel,
        ?NotificationTemplateSetting $template,
        string $title,
        string $content,
        array $recipients
    ): string {
        $notificationMessage = NotificationMessage::create($event, $channel, $template, $title, $content);
        foreach ($recipients as $user) {
            $recipient = NotificationRecipient::create($notificationMessage, $user);
            $notificationMessage->addRecipient($recipient);
        }

        $this->notificationMessageWriter->save($notificationMessage);

        return $notificationMessage->getUUID()->toString();
    }
}
