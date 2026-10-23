<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Channel;

use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Module\System\Domain\Interface\WebSocket\WebSocketPusherInterface;
use App\Module\System\Notification\Domain\Channel\InternalNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class InternalNotificationChannelHandler implements NotificationChannelHandlerInterface
{
    use ClassNameExtractorTrait;

    public function __construct(
        private WebSocketPusherInterface $websocketPusher,
        private LoggerInterface $logger,
        private Security $security,
    ) {
    }

    public function supports(NotificationChannelSetting $channel): bool
    {
        return $channel->getChannelCode() === InternalNotificationChannel::getChanelCode();
    }

    public function send(NotificationEventSetting $event, array $recipients, string $title, string $content, array $payload = []): void
    {
        try {
            $eventName = $event->getEventName();
            $message = [
                'subscriberType' => InternalNotificationChannel::getChanelCode(),
                'event'          => $eventName,
                'title'          => $title,
                'content'        => $content,
            ];

            foreach ($recipients as $recipient) {
                $this->websocketPusher->pushToUser(
                    userUUID: $recipient->getUuid(),
                    event: $eventName,
                    payload: $message
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('RealTime notification failed :(', ['exception' => $e,]);
        }
    }
}
