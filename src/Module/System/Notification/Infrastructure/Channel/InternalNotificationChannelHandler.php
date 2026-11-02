<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Channel;

use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Module\Messaging\Application\Command\NotifySystemNotificationRecipients;
use App\Module\System\Domain\Interface\WebSocket\WebSocketPusherInterface;
use App\Module\System\Notification\Domain\Channel\InternalNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class InternalNotificationChannelHandler implements NotificationChannelHandlerInterface
{
    use ClassNameExtractorTrait;

    public function __construct(
        private WebSocketPusherInterface $websocketPusher,
        private LoggerInterface $logger,
        private Security $security,
        #[Autowire(service: 'command.bus')] private MessageBusInterface $commandBus,
    ) {
    }

    public function supports(NotificationChannelSetting $channel): bool
    {
        return $channel->getChannelCode() === InternalNotificationChannel::getChanelCode();
    }

    public function send(NotificationEventSetting $event, array $recipients, string $title, string $content, array $payload = []): void
    {
        $recipientUuids = [];

        try {
            $eventName = $event->getEventName();
            $message = [
                'subscriberType' => InternalNotificationChannel::getChanelCode(),
                'event'          => $eventName,
                'title'          => $title,
                'content'        => $content,
            ];

            foreach ($recipients as $recipient) {
                $userUuid = method_exists($recipient, 'getUUID')
                    ? $recipient->getUUID()->toString()
                    : (string) $recipient->getUuid();
                $recipientUuids[] = $userUuid;
                $this->websocketPusher->pushToUser(
                    userUUID: $userUuid,
                    event: $eventName,
                    payload: $message
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('RealTime notification failed :(', ['exception' => $e,]);
        }

        $recipientUuids = array_values(array_unique(array_filter($recipientUuids)));
        if ($recipientUuids === []) {
            return;
        }

        $notificationMessageUuid = (string) ($payload['notificationMessageUUID'] ?? '');
        $preview = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117).'...';
        }

        try {
            $this->commandBus->dispatch(
                new NotifySystemNotificationRecipients(
                    $notificationMessageUuid,
                    $title,
                    $preview,
                    $recipientUuids,
                    $event->getEventName(),
                ),
                [new DispatchAfterCurrentBusStamp()],
            );
        } catch (\Throwable $e) {
            $this->logger->error('Mobile push notification enqueue failed :(', ['exception' => $e]);
        }
    }
}
