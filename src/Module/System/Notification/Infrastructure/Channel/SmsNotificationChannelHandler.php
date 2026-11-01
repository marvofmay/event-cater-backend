<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Channel;

use App\Module\System\Notification\Domain\Channel\SmsNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelHandlerInterface;
use App\Module\System\Application\Command\Sms\SendBrevoSmsCommand;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SmsNotificationChannelHandler implements NotificationChannelHandlerInterface
{
    public function __construct(#[Autowire(service: 'command.bus')] private MessageBusInterface $bus)
    {
    }

    public function supports(NotificationChannelSetting $channel): bool
    {
        return $channel->getChannelCode() === SmsNotificationChannel::getChanelCode();
    }

    public function send(NotificationEventSetting $event, array $recipients, string $title, string $content, array $payload = []): void
    {
        foreach ($recipients as $user) {
            foreach ($user->getEmployee()?->getContacts() ?? [] as $contact) {
                if ('phone' !== $contact->getType()) {
                    continue;
                }
                $recipient = preg_replace('/[\s().-]/', '', $contact->getData());
                if (!preg_match('/^\+?[1-9][0-9]{7,14}$/D', $recipient)) {
                    continue;
                }
                $messageId = $payload['notificationMessageUUID'] ?? Uuid::uuid4()->toString();
                $id = Uuid::uuid5(Uuid::NAMESPACE_URL, $messageId.':'.$user->getUUID()->toString())->toString();
                $this->bus->dispatch(new SendBrevoSmsCommand($id, $user->getUUID()->toString(), $recipient, trim(strip_tags(html_entity_decode($title."\n".$content, ENT_QUOTES | ENT_HTML5, 'UTF-8')))));
                break;
            }
        }
    }
}
