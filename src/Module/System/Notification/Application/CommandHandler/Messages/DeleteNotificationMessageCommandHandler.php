<?php

namespace App\Module\System\Notification\Application\CommandHandler\Messages;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Notification\Application\Command\Message\DeleteNotificationCommand;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageDeletedEvent;
use App\Module\System\Notification\Domain\Exception\NotificationRecipientNotFoundException;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageDeleterInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteNotificationMessageCommandHandler
{
    public function __construct(
        private NotificationRecipientReaderInterface $notificationRecipientRepository,
        private NotificationMessageDeleterInterface $notificationMessageDeleter,
        private EventDispatcherInterface $eventDispatcher,
        private MessageService $messageService,
    ) {
    }

    /**
     * @throws NotificationRecipientNotFoundException
     */
    public function __invoke(DeleteNotificationCommand $command): void
    {
        try {
            $notificationRecipient = $this->notificationRecipientRepository
                ->getUnreadNotificationRecipientByUUID($command->notificationUUID);
            $notificationMessage = $notificationRecipient->getMessage();
        } catch (NoResultException $exception) {
            throw NotificationRecipientNotFoundException::fromNotificationUUID(
                $this->messageService,
                $command->notificationUUID,
                $exception
            );
        }

        $this->notificationMessageDeleter->delete($notificationMessage);

        $this->eventDispatcher->dispatch(new NotificationMessageDeletedEvent([
            DeleteNotificationCommand::NOTIFICATION_UUID => $command->notificationUUID,
        ]));
    }
}
