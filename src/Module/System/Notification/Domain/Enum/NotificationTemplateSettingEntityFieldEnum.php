<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Enum;

enum NotificationTemplateSettingEntityFieldEnum: string
{
    case UUID = 'uuid';
    case EVENT_NANE = 'eventName';
    case CHANNEL_CODE = 'channelCode';
    case TITLE = 'title';
    case CONTENT = 'content';
    case IS_DEFAULT = 'isDefault';
    case IS_ACTIVE = 'isActive';
}
