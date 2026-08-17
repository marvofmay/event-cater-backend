<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\User;

use App\Common\Validator\Constraints\MinMaxLength;
use App\Common\Validator\Constraints\NotBlank;

final class ChangeUserPersonalDataDTO
{
    #[NotBlank(message: [
        'text' => 'Employee.firstName.required',
        'domain' => 'employees',
    ])]
    #[MinMaxLength(min: 3, max: 50, message: [
        'tooShort' => 'employee.firstName.minimumLength',
        'tooLong' => 'employee.firstName.maximumLength',
        'domain' => 'employees',
    ])]
    public string $firstName {
        get {
            return $this->firstName;
        }
    }

    #[NotBlank(message: [
        'text' => 'Employee.lastName.required',
        'domain' => 'employees',
    ])]
    #[MinMaxLength(min: 3, max: 50, message: [
        'tooShort' => 'employee.lastName.minimumLength',
        'tooLong' => 'employee.lastName.maximumLength',
        'domain' => 'employees',
    ])]
    public string $lastName {
        get {
            return $this->lastName;
        }
    }
}
