<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\CommandHandler;

use App\Module\Messaging\Application\Command\NotifySystemNotificationRecipients;
use App\Module\Messaging\Domain\Repository\PushTokenRepository;
use App\Module\Messaging\Infrastructure\Push\ExpoPushSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class NotifySystemNotificationRecipientsHandler
{
    public function __construct(
        private PushTokenRepository $tokens,
        private ExpoPushSender $expoPushSender,
    ) {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function __invoke(NotifySystemNotificationRecipients $command): void
    {
        $devices = $this->tokens->findByUserUuids($command->recipientUuids);
        if ($devices === []) {
            return;
        }

        $pushTokens = array_map(static fn ($device) => $device->getToken(), $devices);
        $this->expoPushSender->send(
            $pushTokens,
            $command->title !== '' ? $command->title : 'Nowe powiadomienie',
            $command->body !== '' ? $command->body : 'Masz nowe powiadomienie w EventCater.',
            [
                'type' => 'system_notification',
                'notificationMessageUuid' => $command->notificationMessageUuid,
                'event' => $command->eventName,
            ],
            'notifications',
        );
    }
}
