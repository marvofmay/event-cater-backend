<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Channel;

use App\Module\Company\Domain\Service\Email\EmailService;
use App\Module\System\Notification\Domain\Channel\EmailNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelHandlerInterface;

final readonly class EmailNotificationChannelHandler implements NotificationChannelHandlerInterface
{
    public function __construct(private EmailService $emailService)
    {
    }

    public function supports(NotificationChannelSetting $channel): bool
    {
        return $channel->getChannelCode() === EmailNotificationChannel::getChanelCode();
    }

    public function send(NotificationEventSetting $event, array $recipients, string $title, string $content, array $payload = []): void
    {
        $emails = array_values(array_filter(array_map(static fn ($user) => $user->getEmail(), $recipients)));
        if ($emails === []) {
            return;
        }

        $this->emailService->sendEmail(
            recipients: $emails,
            subject: $title,
            templateName: 'emails/notification.html.twig',
            context: [
                'content' => $content,
            ],
        );
    }
}
