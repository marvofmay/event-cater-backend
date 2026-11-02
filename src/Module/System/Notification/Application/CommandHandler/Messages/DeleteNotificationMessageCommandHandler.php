<?php

namespace App\Module\System\Notification\Application\CommandHandler\Messages;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Entity\User;
use App\Module\System\Notification\Application\Command\Message\DeleteNotificationCommand;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageDeletedEvent;
use App\Module\System\Notification\Domain\Exception\NotificationRecipientNotFoundException;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientDeleterInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteNotificationMessageCommandHandler
{
    public function __construct(
        private NotificationRecipientReaderInterface $notificationRecipientRepository,
        private NotificationRecipientDeleterInterface $notificationRecipientDeleter,
        private EventDispatcherInterface $eventDispatcher,
        private MessageService $messageService,
        private Security $security,
    ) {
    }

    /**
     * @throws NotificationRecipientNotFoundException
     */
    public function __invoke(DeleteNotificationCommand $command): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw NotificationRecipientNotFoundException::fromNotificationUUID(
                $this->messageService,
                $command->notificationUUID
            );
        }

        try {
            $notificationRecipient = $this->notificationRecipientRepository
                ->getOwnedNotificationRecipientByUUID(
                    $command->notificationUUID,
                    $user->getUUID()->toString(),
                );
        } catch (NoResultException $exception) {
            throw NotificationRecipientNotFoundException::fromNotificationUUID(
                $this->messageService,
                $command->notificationUUID,
                $exception
            );
        }

        // Usuwamy wpis w skrzynce użytkownika (przeczytany albo nie), nie całą wspólną wiadomość.
        $this->notificationRecipientDeleter->delete($notificationRecipient);

        $this->eventDispatcher->dispatch(new NotificationMessageDeletedEvent([
            DeleteNotificationCommand::NOTIFICATION_UUID => $command->notificationUUID,
        ]));
    }
}
