<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\Command\Message;

use App\Common\Domain\Interface\CommandInterface;

final readonly class MarkAsReadMultipleNotificationsCommand implements CommandInterface
{
    public const string NOTIFICATIONS_UUIDS = 'notificationsUUIDs';
    public function __construct(public array $notificationsUUIDS)
    {
    }
}
