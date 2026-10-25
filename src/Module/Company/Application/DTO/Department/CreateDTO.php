<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\Department;

use App\Common\Application\DTO\AddressDTO;
use App\Common\Validator\Constraints\MinMaxLength;
use App\Common\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;

class CreateDTO
{
    #[NotBlank(message: [
        'text' => 'department.name.required',
        'domain' => 'departments',
    ])]
    #[MinMaxLength(
        min: 3,
        max: 200,
        message: [
            'tooShort' => 'department.name.minimumLength',
            'tooLong' => 'department.name.maximumLength',
            'domain' => 'departments'
        ]
    )]
    public string $name {
        get {
            return $this->name;
        }
    }

    #[NotBlank(message: [
        'text' => 'department.internalCode.required',
        'domain' => 'departments',
    ])]
    #[MinMaxLength(min: 3, max: 50, message: [
        'tooShort' => 'department.internalCode.minimumLength',
        'tooLong' => 'department.internalCode.maximumLength',
        'domain' => 'departments',
    ])]
    public string $internalCode {
        get {
            return $this->internalCode;
        }
    }

    #[MinMaxLength(min: 3, max: 500, message: [
        'tooShort' => 'department.description.minimumLength',
        'tooLong' => 'department.description.maximumLength',
        'domain' => 'departments',
    ])]
    public ?string $description = null {
        get {
            return $this->description;
        }
    }

    #[Assert\Type(
        type: 'bool',
    )]
    public bool $active = true {
        get {
            return $this->active;
        }
    }

    #[NotBlank(message: [
        'text' => 'company.uuid.required',
        'domain' => 'companies',
    ])]
    #[Assert\Uuid(message: 'company.invalidUUID')]
    public ?string $companyUUID = null {
        get {
            return $this->companyUUID;
        }
    }

    #[Assert\Uuid(message: 'department.invalidUUID')]
    public ?string $parentDepartmentUUID = null {
        get {
            return $this->parentDepartmentUUID;
        }
    }

    #[Assert\All([
        new Assert\Type(type: 'string'),
    ])]
    #[Assert\Type('array')]
    #[Assert\Count(
        max: 3,
        maxMessage: 'phones.max'
    )]
    public ?array $phones = [] {
        get {
            return $this->phones;
        }
    }

    #[Assert\All([
        new Assert\Type(type: 'string'),
    ])]
    #[Assert\Type('array')]
    #[Assert\Count(
        max: 3,
        maxMessage: 'emails.max'
    )]
    public ?array $emails = [] {
        get {
            return $this->emails;
        }
    }

    #[Assert\All([
        new Assert\Type(type: 'string'),
    ])]
    #[Assert\Type('array')]
    #[Assert\Count(
        max: 3,
        maxMessage: 'websites.max'
    )]
    public ?array $websites = [] {
        get {
            return $this->websites;
        }
    }

    #[Assert\NotBlank]
    #[Assert\Valid]
    public AddressDTO $address {
        get {
            return $this->address;
        }
    }
}
