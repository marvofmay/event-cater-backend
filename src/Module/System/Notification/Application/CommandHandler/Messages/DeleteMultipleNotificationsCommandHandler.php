<?php

namespace App\Module\System\Notification\Application\CommandHandler\Messages;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Entity\User;
use App\Module\System\Notification\Application\Command\Message\DeleteMultipleNotificationsCommand;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageMultipleDeletedEvent;
use App\Module\System\Notification\Domain\Exception\NotificationMultipleRecipientNotFoundException;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientDeleterInterface;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteMultipleNotificationsCommandHandler
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
     * @throws NotificationMultipleRecipientNotFoundException
     */
    public function __invoke(DeleteMultipleNotificationsCommand $command): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw NotificationMultipleRecipientNotFoundException::fromNotificationUUIDs(
                $this->messageService,
                array_values(array_unique($command->notificationsUUIDS)),
            );
        }

        $notificationUUIDs = array_values(array_unique($command->notificationsUUIDS));
        $notificationRecipients = $this->notificationRecipientRepository
            ->getOwnedNotificationRecipientsByUUIDs(
                $notificationUUIDs,
                $user->getUUID()->toString(),
            );

        $foundUUIDs = array_map(
            static fn ($notificationRecipient): string => $notificationRecipient->getUUID()->toString(),
            $notificationRecipients
        );
        $missingUUIDs = array_values(array_diff($notificationUUIDs, $foundUUIDs));

        if ($missingUUIDs !== []) {
            throw NotificationMultipleRecipientNotFoundException::fromNotificationUUIDs(
                $this->messageService,
                $missingUUIDs
            );
        }

        foreach ($notificationRecipients as $notificationRecipient) {
            $this->notificationRecipientDeleter->delete($notificationRecipient);
        }

        $this->eventDispatcher->dispatch(new NotificationMessageMultipleDeletedEvent([
            DeleteMultipleNotificationsCommand::NOTIFICATIONS_UUIDS => $command->notificationsUUIDS,
        ]));
    }
}
