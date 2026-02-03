<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;
use App\Common\Validator\Constraints\NotBlank;

final class ChangeUserContactDTO
{
    #[NotBlank(message: [
        'text' => 'employee.email.required',
        'domain' => 'employees',
    ])]
    #[Assert\Email(message: 'email.invalid')]
    public string $email {
        get {
            return $this->email;
        }
    }


    #[Assert\All([
        new Assert\Type(type: 'string'),
    ])]
    #[Assert\Type('array')]
    #[Assert\Count(
        min: 1,
        max: 3,
        minMessage: 'phones.min',
        maxMessage: 'phones.max'
    )]
    public ?array $phones = [] {
        get {
            return $this->phones;
        }
    }
}
