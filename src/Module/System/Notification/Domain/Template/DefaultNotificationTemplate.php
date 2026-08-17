<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Template;

use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateInterface;

class DefaultNotificationTemplate implements NotificationTemplateInterface
{
    public function isDefault(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Import {{importKind}}';
    }

    public function getContent(): string
    {
        return 'Import {{importKind}} zakończył się statusem: {{importStatus}}.';
    }
}
