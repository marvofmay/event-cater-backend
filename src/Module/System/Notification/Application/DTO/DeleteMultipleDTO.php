<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class DeleteMultipleDTO
{
    #[Assert\NotBlank(message: 'notification.delete.multiple.selectedUUIDsRequired')]
    #[Assert\All([
        new Assert\Uuid(message: 'notification.delete.invalidUUID'),
    ])]
    public array $notificationsUUIDs = [] {
        get {
            return $this->notificationsUUIDs;
        }
    }
}
