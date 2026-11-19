<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\Company;

use App\Common\Application\DTO\AddressDTO;
use App\Common\Domain\Interface\DataTransferObjectInterface;
use App\Common\Validator\Constraints\MinMaxLength;
use App\Common\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;

class CreateDTO implements DataTransferObjectInterface
{
    #[NotBlank(message: [
        'text' => 'company.fullName.required',
        'domain' => 'companies',
    ])]
    #[MinMaxLength(min: 3, max: 500, message: [
        'tooShort' => 'company.fullName.minimumLength',
        'tooLong' => 'company.fullName.maximumLength',
        'domain' => 'companies',
    ])]
    public string $fullName = '' {
        get {
            return $this->fullName;
        }
    }

    public ?string $shortName = null {
        get {
            return $this->shortName;
        }
    }

    #[MinMaxLength(min: 0, max: 50, message: [
        'tooLong' => 'company.internalCode.maximumLength',
        'domain' => 'companies',
    ])]
    public ?string $internalCode = null {
        get {
            return $this->internalCode;
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

    #[Assert\Uuid(message: 'invalidUUID')]
    public ?string $parentCompanyUUID = null {
        get {
            return $this->parentCompanyUUID;
        }
    }

    #[NotBlank(message: [
        'text' => 'company.industryUUID.required',
        'domain' => 'companies',
    ])]
    #[Assert\Uuid(message: 'invalidUUID')]
    public ?string $industryUUID = null {
        get {
            return $this->industryUUID;
        }
    }

    #[NotBlank(message: [
        'text' => 'company.nip.required',
        'domain' => 'companies',
    ])]
    #[MinMaxLength(min: 10, max: 10, message: [
        'tooShort' => 'company.nip.minimumLength',
        'tooLong' => 'company.nip.maximumLength',
        'exactMessage' => 'company.nip.exactLength',
        'domain' => 'companies',
    ])]
    public ?string $nip = null {
        get {
            return $this->nip;
        }
    }

    #[NotBlank(message: [
        'text' => 'company.regon.required',
        'domain' => 'companies',
    ])]
    #[MinMaxLength(min: 9, max: 14, message: [
        'tooShort' => 'company.regon.minimumLength',
        'tooLong' => 'company.regon.maximumLength',
        'domain' => 'companies',
    ])]
    public ?string $regon = null {
        get {
            return $this->regon;
        }
    }

    #[MinMaxLength(min: 3, max: 500, message: [
        'tooShort' => 'company.description.minimumLength',
        'tooLong' => 'company.description.maximumLength',
        'domain' => 'companies',
    ])]
    public ?string $description = null {
        get {
            return $this->description;
        }
    }

    #[Assert\All([
        new Assert\Type(type: 'string'),
        new Assert\Length(max: 100, maxMessage: 'contact.data.max'),
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
        new Assert\Length(max: 100, maxMessage: 'contact.data.max'),
        new Assert\Email(message: 'email.invalid'),
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
        new Assert\Length(max: 100, maxMessage: 'contact.data.max'),
        new Assert\Url(message: 'website.invalidProtocol', protocols: ['http', 'https']),
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
