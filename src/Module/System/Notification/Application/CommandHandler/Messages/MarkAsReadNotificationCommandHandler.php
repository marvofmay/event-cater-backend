<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\CommandHandler\Messages;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Notification\Application\Command\Message\MarkAsReadNotificationCommand;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageMarkedAsReadEvent;
use App\Module\System\Notification\Domain\Exception\NotificationUnreadNotFoundException;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientUpdaterInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class MarkAsReadNotificationCommandHandler
{
    public function __construct(
        private NotificationRecipientReaderInterface $notificationRecipientRepository,
        private NotificationRecipientUpdaterInterface $notificationRecipientUpdater,
        private EventDispatcherInterface $eventDispatcher,
        private MessageService $messageService,
    ) {
    }

    /**
     * @throws NotificationUnreadNotFoundException
     */
    public function __invoke(MarkAsReadNotificationCommand $command): void
    {
        $notificationUUID = $command->notificationUUID;
        try {
            $unreadNotification = $this->notificationRecipientRepository->getUnreadNotificationRecipientByUUID($notificationUUID);
        } catch (NoResultException $exception) {
            throw NotificationUnreadNotFoundException::fromNotificationUUID(
                $this->messageService,
                $notificationUUID,
                $exception
            );
        }

        $this->notificationRecipientUpdater->markAsRead($unreadNotification);
        $this->eventDispatcher->dispatch(new NotificationMessageMarkedAsReadEvent([
            MarkAsReadNotificationCommand::NOTIFICATION_UUID => $command->notificationUUID,
        ]));
    }
}
