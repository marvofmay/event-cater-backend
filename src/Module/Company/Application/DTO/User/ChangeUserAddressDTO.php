<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\User;

use App\Common\Application\DTO\AddressDTO;
use App\Common\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeUserAddressDTO
{
    #[NotBlank(message: [
        'text' => 'employee.address.required',
        'domain' => 'employees',
    ])]
    #[Assert\Valid]
    public AddressDTO $address {
        get {
            return $this->address;
        }
    }
}
