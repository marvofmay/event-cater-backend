<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\Console;

use App\Module\System\Notification\Application\Event\Template\NotificationTemplateSettingCreatedEvent;
use App\Module\System\Notification\Domain\Enum\NotificationChannelSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Enum\NotificationEventSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Enum\NotificationTemplateSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Factory\NotificationTemplateFactory;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelSettingReaderInterface;
use App\Module\System\Notification\Domain\Interface\Event\NotificationEventSettingReaderInterface;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use App\Module\System\Notification\Domain\Service\Template\NotificationTemplateSettingCreator;
use App\Module\System\Notification\Domain\Service\Template\NotificationTemplateSettingUpdater;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'app:add-record-to-notification-template-setting-table')]
#[AutoconfigureTag('app.command.initialize-system-default-data', ['priority' => -200])]
final class AddRecordToNotificationTemplateSettingTableCommand extends Command
{
    private const string DESCRIPTION = 'Add missing records to "notification_template_setting" table';
    private const string HELP = 'This command ensures that all NotificationTemplateEnum values exist in the "notification_template_setting table"';
    private const string SUCCESS_MESSAGE = '"notification_template_setting" table has been updated successfully!';
    private const string INFO_ADDED_MESSAGE = 'Added missing notification templates';
    private const string INFO_NO_ADDED_MESSAGE = 'No new notification templates to add';

    public function __construct(
        private readonly NotificationTemplateSettingReaderInterface $notificationTemplateSettingReaderRepository,
        private readonly NotificationEventSettingReaderInterface $notificationEventSettingReaderRepository,
        private readonly NotificationChannelSettingReaderInterface $notificationChannelSettingReaderRepository,
        private readonly NotificationTemplateSettingCreator $notificationTemplateSettingCreator,
        private readonly NotificationTemplateSettingUpdater $notificationTemplateSettingUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationTemplateFactory $notificationTemplateFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::DESCRIPTION)
            ->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Checking and updating "notification_template_setting" table...');

        $existingTemplateValues = array_map(
            fn ($setting) => sprintf(
                '%s|%s|%s',
                $setting->getEvent()->getEventName(),
                $setting->getChannel()->getChannelCode(),
                $setting->isDefault() ? 'default' : 'custom'
            ),
            $this->notificationTemplateSettingReaderRepository->getAll()->toArray()
        );

        $existingTemplateValues = array_flip($existingTemplateValues);
        $templateCodesToPersist = [];


        $events = $this->notificationEventSettingReaderRepository->getAll();
        $uniqueEvents = [];
        foreach ($events as $event) {
            $uniqueEvents[$event->getEventName()] = $event;
        }
        $uniqueEvents = array_values($uniqueEvents);

        $channels = $this->notificationChannelSettingReaderRepository->getAll();
        $uniqueChannels = [];
        foreach ($channels as $channel) {
            $uniqueChannels[$channel->getChannelCode()] = $channel;
        }
        $uniqueChannels = array_values($uniqueChannels);

        $templates = $this->notificationTemplateFactory->all();

        foreach ($uniqueEvents as $event) {
            foreach ($uniqueChannels as $channel) {
                foreach ($templates as $template) {
                    $scope = $template->isDefault() ? 'default' : 'custom';
                    $key = sprintf('%s|%s|%s', $event->getEventName(), $channel->getChannelCode(), $scope);

                    if (isset($existingTemplateValues[$key])) {
                        continue;
                    }

                    $this->notificationTemplateSettingCreator->create(
                        $event,
                        $channel,
                        $template->getTitle(),
                        $template->getContent(),
                        $template->isDefault(),
                        $template->isDefault()
                    );

                    $this->eventDispatcher->dispatch(new NotificationTemplateSettingCreatedEvent([
                        NotificationEventSettingEntityFieldEnum::EVENT_NAME->value => $event->getEventName(),
                        NotificationChannelSettingEntityFieldEnum::CHANNEL_CODE->value => $channel->getChannelCode(),
                        NotificationTemplateSettingEntityFieldEnum::TITLE->value => $template->getTitle(),
                        NotificationTemplateSettingEntityFieldEnum::CONTENT->value => $template->getContent(),
                        NotificationTemplateSettingEntityFieldEnum::IS_DEFAULT->value => $template->isDefault(),
                        NotificationTemplateSettingEntityFieldEnum::IS_ACTIVE->value => $template->isDefault(),
                    ]));

                    $templateCodesToPersist[] = $key;
                    $existingTemplateValues[$key] = true;
                }
            }
        }

        if (!empty($templateCodesToPersist)) {
            $output->writeln(sprintf(
                '<info>%s:</info> %s',
                self::INFO_ADDED_MESSAGE,
                implode(', ', $templateCodesToPersist)
            ));
            $output->writeln(sprintf('<info>%s</info>', self::SUCCESS_MESSAGE));
        } else {
            $output->writeln(sprintf('<comment>%s</comment>', self::INFO_NO_ADDED_MESSAGE));
        }

        $defaultTemplate = $this->notificationTemplateFactory->getTemplate(true);
        $updatedDefaults = 0;
        if (null !== $defaultTemplate) {
            foreach ($this->notificationTemplateSettingReaderRepository->getAll() as $setting) {
                if (!$setting->isDefault() || 'default title' !== $setting->getTitle()) {
                    continue;
                }

                $this->notificationTemplateSettingUpdater->update(
                    $setting,
                    $defaultTemplate->getTitle(),
                    $defaultTemplate->getContent(),
                    true,
                    true
                );
                ++$updatedDefaults;
            }
        }

        if ($updatedDefaults > 0) {
            $output->writeln(sprintf('<info>Updated %d default notification templates</info>', $updatedDefaults));
        }

        return Command::SUCCESS;
    }
}
