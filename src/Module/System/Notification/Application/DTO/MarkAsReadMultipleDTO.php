<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class MarkAsReadMultipleDTO
{
    #[Assert\NotBlank(message: 'notification.markAsRead.multiple.selectedUUIDsRequired')]
    #[Assert\All([
        new Assert\Uuid(message: 'uuid.invalid'),
    ])]
    public array $notificationsUUIDs = [] {
        get {
            return $this->notificationsUUIDs;
        }
    }
}
