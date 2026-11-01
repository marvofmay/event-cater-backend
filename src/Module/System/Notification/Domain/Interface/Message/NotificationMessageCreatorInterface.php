<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Message;

use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Entity\NotificationTemplateSetting;

interface NotificationMessageCreatorInterface
{
    public function create(
        NotificationEventSetting $event,
        NotificationChannelSetting $channel,
        ?NotificationTemplateSetting $template,
        string $title,
        string $content,
        array $recipients
    ): string;
}
