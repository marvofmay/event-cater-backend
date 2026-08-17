<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Recipient;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;

interface NotificationRecipientReaderInterface
{
    public function getUnreadNotificationRecipientByUUID(string $uuid): NotificationRecipient;
    public function getUnreadNotificationRecipientsByUUIDs(array $uuids): array;

    public function countUnreadNotificationMessagesForUser(string $userUUID): int;
}
