<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\CommandHandler\Event;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Notification\Application\Command\Event\UpdateNotificationEventChannelsCommand;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingWriterInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateNotificationEventChannelsCommandHandler extends CommandHandlerAbstract
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationTemplateSettingReaderInterface $templates,
        private readonly NotificationTemplateSettingWriterInterface $templateWriter,
        private readonly PreferenceService $preferences,
    ) {
    }

    public function __invoke(UpdateNotificationEventChannelsCommand $command): void
    {
        $event = $this->em->find(NotificationEventSetting::class, $command->eventName, LockMode::PESSIMISTIC_WRITE);
        if ($event === null) {
            throw new PlanningResourceNotFound('Nie znaleziono zdarzenia powiadomienia.');
        }

        $master = $this->preferences->masterChannels();
        foreach ($command->channels as $channelCode => $enabled) {
            if (!is_string($channelCode) || !array_key_exists($channelCode, $master)) {
                throw new BusinessRuleViolation(sprintf('Nieznany kanał powiadomień: %s.', (string) $channelCode));
            }
            if (!$master[$channelCode] && $enabled) {
                throw new BusinessRuleViolation(sprintf('Kanał %s jest wyłączony globalnie.', $channelCode));
            }

            $default = $this->templates->getByEventNameChannelCodeAndDefault($command->eventName, $channelCode, true);
            $custom = $this->templates->getByEventNameChannelCodeAndDefault($command->eventName, $channelCode, false);
            if ($default === null && $custom === null) {
                throw new PlanningResourceNotFound(sprintf('Brak szablonów dla kanału %s.', $channelCode));
            }

            if ($enabled) {
                $activate = ($custom !== null && $custom->isActive()) ? $custom : ($default ?? $custom);
                $deactivate = $activate === $default ? $custom : $default;
                $activate?->activate();
                $deactivate?->deactivate();
                if ($activate !== null) {
                    $this->templateWriter->save($activate);
                }
                if ($deactivate !== null) {
                    $this->templateWriter->save($deactivate);
                }
            } else {
                foreach ([$default, $custom] as $template) {
                    if ($template === null) {
                        continue;
                    }
                    $template->deactivate();
                    $this->templateWriter->save($template);
                }
            }
        }
    }
}
