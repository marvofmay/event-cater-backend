<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\Role;

use Symfony\Component\Validator\Constraints as Assert;

final class DeleteMultipleDTO
{
    #[Assert\NotBlank(message: 'role.delete.multiple.selectedUUIDsRequired')]
    #[Assert\All([
        new Assert\Uuid(message: 'uuid.invalid'),
    ])]
    public array $rolesUUIDs = [] {
        get {
            return $this->rolesUUIDs;
        }
    }
}
