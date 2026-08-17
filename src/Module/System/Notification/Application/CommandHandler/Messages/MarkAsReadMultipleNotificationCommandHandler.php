<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\CommandHandler\Messages;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Notification\Application\Command\Message\MarkAsReadMultipleNotificationsCommand;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageMultipleMarkedAsReadEvent;
use App\Module\System\Notification\Domain\Exception\NotificationUnreadMultipleNotFoundException;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientUpdaterInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class MarkAsReadMultipleNotificationCommandHandler
{
    public function __construct(
        private NotificationRecipientReaderInterface $notificationRecipientRepository,
        private NotificationRecipientUpdaterInterface $notificationRecipientUpdater,
        private EventDispatcherInterface $eventDispatcher,
        private MessageService $messageService,
    ) {
    }

    /**
     * @throws NotificationUnreadMultipleNotFoundException
     */
    public function __invoke(MarkAsReadMultipleNotificationsCommand $command): void
    {
        $notificationUUIDs = array_values(array_unique($command->notificationsUUIDS));
        $unreadNotifications = $this->notificationRecipientRepository
            ->getUnreadNotificationMessagesByUUIDs($notificationUUIDs);

        $foundUUIDs = array_map(
            static fn ($notification): string => $notification->getUUID()->toString(),
            $unreadNotifications
        );
        $missingUUIDs = array_values(array_diff($notificationUUIDs, $foundUUIDs));

        if ($missingUUIDs !== []) {
            throw NotificationUnreadMultipleNotFoundException::fromNotificationUUIDs(
                $this->messageService,
                $missingUUIDs
            );
        }

        foreach ($unreadNotifications as $unreadNotification) {
            $this->notificationRecipientUpdater->markAsRead($unreadNotification);
        }

        $this->eventDispatcher->dispatch(new NotificationMessageMultipleMarkedAsReadEvent([
            MarkAsReadMultipleNotificationsCommand::NOTIFICATIONS_UUIDS => $command->notificationsUUIDS,
        ]));
    }
}
