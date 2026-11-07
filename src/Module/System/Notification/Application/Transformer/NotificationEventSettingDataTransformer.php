<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\Transformer;

use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Common\Domain\Interface\DataTransformerInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Notification\Application\QueryHandler\Event\ListNotificationEventSettingQueryHandler;
use App\Module\System\Notification\Domain\Catalog\NotificationDefaultContent;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Enum\NotificationEventSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Factory\NotificationEventFactory;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;

class NotificationEventSettingDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly NotificationEventFactory $eventFactory,
        private readonly MessageService $messages,
        private readonly NotificationTemplateSettingReaderInterface $templates,
        private readonly PreferenceService $preferences,
    ) {
    }

    public static function supports(): string
    {
        return ListNotificationEventSettingQueryHandler::class;
    }

    public function transformToArray(NotificationEventSetting $notificationEventSetting): array
    {
        $eventName = $notificationEventSetting->getEventName();
        $definition = $this->eventFactory->getEvent($eventName);
        $labelKey = $definition?->getLabel() ?? ('notification.event.'.$eventName);
        $label = $this->messages->get($labelKey, [], 'notifications');
        if ($label === $labelKey) {
            $label = $eventName;
        }

        $master = $this->preferences->masterChannels();
        $channels = [];
        foreach (array_keys($master) as $channelCode) {
            if (!$master[$channelCode]) {
                continue;
            }
            $default = $this->templates->getByEventNameChannelCodeAndDefault($eventName, $channelCode, true);
            $custom = $this->templates->getByEventNameChannelCodeAndDefault($eventName, $channelCode, false);
            $channels[] = [
                'channelCode' => $channelCode,
                'active' => ($default?->isActive() ?? false) || ($custom?->isActive() ?? false),
                'globallyEnabled' => true,
            ];
        }

        return [
            NotificationEventSettingEntityFieldEnum::EVENT_NAME->value => $eventName,
            'label' => $label,
            NotificationEventSettingEntityFieldEnum::ENABLED->value => $notificationEventSetting->isEnabled(),
            'channels' => $channels,
            'variables' => NotificationDefaultContent::variablesFor($eventName),
            TimeStampableEntityFieldEnum::CREATED_AT->value => $notificationEventSetting->createdAt->format('Y-m-d H:i:s'),
            TimeStampableEntityFieldEnum::UPDATED_AT->value => $notificationEventSetting->updatedAt?->format('Y-m-d H:i:s'),
            TimeStampableEntityFieldEnum::DELETED_AT->value => $notificationEventSetting->deletedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
