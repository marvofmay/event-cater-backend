<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\Employee;

use App\Module\Company\Application\ReadModel\Address\AddressView;
use App\Module\Company\Application\ReadModel\Avatar\AvatarView;
use App\Module\Company\Application\ReadModel\Contact\ContactView;
use App\Module\Company\Application\ReadModel\EmploymentView\EmploymentView;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Entity\Employee;

final readonly class EmployeeView
{
    public function __construct(
        public string $uuid,
        public string $firstName,
        public string $lastName,
        public EmploymentView $employment,
        public ?AddressView $address,
        public array $contacts,
        public ?AvatarView $avatar = null,
    ) {
    }

    public static function fromEmployee(
        Employee $employee,
        string $avatarType = 'default',
        ?string $defaultAvatar = null,
        ?string $avatarPath = null
    ): self {
        return new self(
            uuid: $employee->getUUID()->toString(),
            firstName: $employee->getFirstName(),
            lastName: $employee->getLastName(),
            employment: EmploymentView::fromEmployee($employee),
            address: $employee->getAddress()
                ? AddressView::fromAddress($employee->getAddress())
                : null,
            contacts: array_map(
                fn (Contact $contact) => ContactView::fromContact($contact),
                $employee->getContacts()->toArray()
            ),
            avatar: new AvatarView(
                avatarType: $avatarType,
                defaultAvatar: $defaultAvatar,
                avatarPath: $avatarPath
            )
        );
    }
}
