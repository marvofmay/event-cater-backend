<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\Command\Message;

use App\Common\Domain\Interface\CommandInterface;

final readonly class DeleteNotificationCommand implements CommandInterface
{
    public const string NOTIFICATION_UUID = 'notificationUUID';
    public function __construct(public string $notificationUUID)
    {
    }
}
