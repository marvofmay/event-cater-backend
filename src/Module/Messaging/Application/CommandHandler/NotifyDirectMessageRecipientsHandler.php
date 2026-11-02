<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\CommandHandler;

use App\Module\Messaging\Application\Command\NotifyDirectMessageRecipients;
use App\Module\Messaging\Domain\Repository\PushTokenRepository;
use App\Module\Messaging\Infrastructure\Push\ExpoPushSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class NotifyDirectMessageRecipientsHandler
{
    public function __construct(
        private PushTokenRepository $tokens,
        private ExpoPushSender $expoPushSender,
    ) {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function __invoke(NotifyDirectMessageRecipients $command): void
    {
        $devices = $this->tokens->findByUserUuids($command->recipientUuids);
        if ($devices === []) {
            return;
        }

        $pushTokens = array_map(static fn ($device) => $device->getToken(), $devices);
        $this->expoPushSender->send(
            $pushTokens,
            $command->title !== '' ? $command->title : 'Nowa wiadomość',
            $command->body !== '' ? $command->body : 'Masz nową wiadomość w EventCater.',
            [
                'type' => 'direct_message',
                'messageUuid' => $command->messageUuid,
            ],
            'messages',
        );
    }
}
