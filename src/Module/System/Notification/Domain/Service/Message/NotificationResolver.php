<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Service\Message;

use App\Common\Domain\Interface\NotifiableEventInterface;
use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageCreatorInterface;
use App\Module\System\Notification\Domain\Interface\Message\NotificationResolveInterface;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use App\Module\System\Notification\Domain\Service\Channel\NotificationChannelDispatcher;
use App\Module\System\Notification\Domain\Service\Event\NotificationEventPayloadDispatcher;
use Psr\Log\LoggerInterface;

final readonly class NotificationResolver implements NotificationResolveInterface
{
    use ClassNameExtractorTrait;

    public function __construct(
        private NotificationTemplateSettingReaderInterface $templateReaderRepository,
        private NotificationMessageCreatorInterface $messageCreator,
        private NotificationChannelDispatcher $channelDispatcher,
        private NotificationEventPayloadDispatcher $payloadDispatcher,
        private PreferenceService $preferences,
        private LoggerInterface $logger,
    ) {
    }

    public function resolve(NotifiableEventInterface $notifiableEvent): void
    {
        [$payload, $recipients] = $this->payloadDispatcher->getPayloadData($notifiableEvent);
        $this->publish($this->getShortClassName($notifiableEvent::class), $payload, $recipients);
    }

    public function publish(string $eventName, array $payload, array $recipients, ?array $allowedChannels = null): void
    {
        if ($recipients === []) {
            return;
        }

        $allowed = $allowedChannels === null
            ? null
            : array_values(array_unique(array_filter($allowedChannels, static fn ($code) => is_string($code) && $code !== '')));
        if ($allowed !== null && $allowed === []) {
            return;
        }

        $templates = $this->templateReaderRepository->getActiveByEventName($eventName);
        foreach ($templates as $template) {
            $event = $template->getEvent();
            if (!$event->isEnabled()) {
                return;
            }
            $channel = $template->getChannel();
            if ($allowed !== null && !in_array($channel->getChannelCode(), $allowed, true)) {
                continue;
            }
            $channelRecipients = $this->preferences->filterRecipients($channel->getChannelCode(), $recipients);
            if ([] === $channelRecipients) {
                continue;
            }
            $title = $this->renderTemplate($template->getTitle(), $payload);
            $content = $this->renderTemplate($template->getContent(), $payload);

            $messageUuid = $this->messageCreator->create($event, $channel, $template, $title, $content, $channelRecipients);
            try {
                $this->channelDispatcher->dispatch(
                    $event,
                    $channel,
                    $channelRecipients,
                    $title,
                    $content,
                    [...$payload, 'notificationMessageUUID' => $messageUuid],
                );
            } catch (\Throwable $pushError) {
                $this->logger->warning('Notification saved, but channel push failed', [
                    'eventName' => $eventName,
                    'channel' => $channel->getChannelCode(),
                    'exception' => $pushError,
                ]);
            }
        }
    }

    public function renderTemplate(string $template, array $payload): string
    {
        foreach ($payload as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }
}
