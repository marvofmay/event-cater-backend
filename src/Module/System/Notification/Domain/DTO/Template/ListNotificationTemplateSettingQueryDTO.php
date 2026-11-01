<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\DTO\Template;

use App\Common\Domain\Abstract\QueryDTOAbstract;

final class ListNotificationTemplateSettingQueryDTO extends QueryDTOAbstract
{
    public ?string $eventName   = null;
    public ?string $channelCode = null;
    public ?string $title       = null;
    public ?string $content     = null;
    public ?bool   $isDefault   = null;
    public ?bool   $isActive    = null;
}
