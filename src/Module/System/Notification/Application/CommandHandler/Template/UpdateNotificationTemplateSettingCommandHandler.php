<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\CommandHandler\Template;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Module\System\Notification\Application\Command\Template\UpdateNotificationTemplateSettingCommand;
use App\Module\System\Notification\Application\Event\Template\NotificationTemplateSettingUpdatedEvent;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use App\Module\System\Notification\Domain\Service\Template\NotificationTemplateSettingUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateNotificationTemplateSettingCommandHandler extends CommandHandlerAbstract
{
    public function __construct(
        private readonly NotificationTemplateSettingUpdater $notificationTemplateSettingUpdater,
        private readonly NotificationTemplateSettingReaderInterface $notificationTemplateSettingReader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
    ) {
    }

    public function __invoke(UpdateNotificationTemplateSettingCommand $command): void
    {
        $event = $this->em->find(\App\Module\System\Notification\Domain\Entity\NotificationEventSetting::class, $command->eventName, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        if ($event === null) {
            throw new \App\Common\Application\Exception\PlanningResourceNotFound('Nie znaleziono zdarzenia powiadomienia.');
        }
        $notificationTemplateSetting = $this->notificationTemplateSettingReader->getByEventNameChannelCodeAndDefault(
            $command->eventName,
            $command->channelCode,
            $command->searchDefault
        );

        if ($notificationTemplateSetting === null) {
            throw new \App\Common\Application\Exception\PlanningResourceNotFound('Nie znaleziono szablonu powiadomienia.');
        }
        if ($command->markAsActive) {
            $other = $this->notificationTemplateSettingReader->getByEventNameChannelCodeAndDefault($command->eventName, $command->channelCode, !$command->searchDefault);
            if ($other !== null) {
                $other->deactivate();
            }
        }

        $this->notificationTemplateSettingUpdater->update(
            notificationTemplateSetting: $notificationTemplateSetting,
            title: $command->title,
            content: $command->content,
            searchDefault: $command->searchDefault,
            markAsActive: $command->markAsActive,
        );

        $this->eventDispatcher->dispatch(new NotificationTemplateSettingUpdatedEvent([
            UpdateNotificationTemplateSettingCommand::EVENT_NAME => $command->eventName,
            UpdateNotificationTemplateSettingCommand::CHANNEL_CODE => $command->channelCode,
            UpdateNotificationTemplateSettingCommand::TITLE => $command->title,
            UpdateNotificationTemplateSettingCommand::CONTENT => $command->content,
            UpdateNotificationTemplateSettingCommand::SEARCH_DEFAULT => $command->searchDefault,
            UpdateNotificationTemplateSettingCommand::MARK_AS_ACTIVE => $command->markAsActive,
        ]));
    }
}
