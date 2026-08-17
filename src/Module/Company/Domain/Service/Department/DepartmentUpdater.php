<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Department;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Interface\Address\AddressWriterInterface;
use App\Module\Company\Domain\Interface\Company\CompanyReaderInterface;
use App\Module\Company\Domain\Interface\Contact\ContactWriterInterface;
use App\Module\Company\Domain\Interface\Department\DepartmentReaderInterface;
use App\Module\Company\Domain\Interface\Department\DepartmentWriterInterface;
use App\Module\Company\Domain\Service\Department\Factory\DepartmentFactory;
use App\Module\Company\Domain\Service\Factory\AddressFactory;
use App\Module\Company\Domain\Service\Factory\ContactFactory;
use Doctrine\Common\Collections\Collection;

final readonly class DepartmentUpdater
{
    public function __construct(
        private DepartmentFactory $departmentFactory,
        private AddressFactory $addressFactory,
        private ContactFactory $contactFactory,
        private CompanyReaderInterface $companyReaderRepository,
        private DepartmentReaderInterface $departmentReaderRepository,
        private DepartmentWriterInterface $departmentWriterRepository,
        private ContactWriterInterface $contactWriterRepository,
        private AddressWriterInterface $addressWriterRepository,
        private EntityReferenceCache $entityReferenceCache,
    ) {
    }

    public function update(DomainEventInterface $event): void
    {
        $department = $this->departmentReaderRepository->getDepartmentByUUID($event->uuid->toString());
        $this->departmentFactory->update($department, $event);

        $this->deleteAddress($department->getAddress());
        $this->deleteContacts($department->getContacts());

        $address = $this->addressFactory->create($event->address);
        $contacts = $this->contactFactory->create($event->phones, $event->emails, $event->websites);

        $company = $this->entityReferenceCache->get(
            Company::class,
            $event->companyUUID->toString(),
            fn (string $uuid) => $this->companyReaderRepository->getCompanyByUUID($uuid)
        );

        $parentDepartment = $event->parentDepartmentUUID?->toString()
            ? $this->entityReferenceCache->get(
                Department::class,
                $event->parentDepartmentUUID->toString(),
                fn (string $uuid) => $this->departmentReaderRepository->getDepartmentByUUID($uuid)
            )
            : null;

        $this->setDepartmentRelations($department, $company, $parentDepartment, $address, $contacts);

        $this->departmentWriterRepository->saveDepartmentInDB($department);
    }

    private function deleteAddress(?Address $address): void
    {
        if (null !== $address) {
            $this->addressWriterRepository->deleteAddressInDB($address, Address::HARD_DELETED_AT);
        }
    }

    private function deleteContacts(Collection $contacts): void
    {
        $this->contactWriterRepository->deleteContacts($contacts, Contact::HARD_DELETED_AT);
    }

    private function setDepartmentRelations(Department $department, Company $company, ?Department $parentDepartment, Address $address, array $contacts): void
    {
        $department->setCompany($company);
        $department->setParentDepartment($parentDepartment);
        $department->setAddress($address);

        foreach ($contacts as $contact) {
            $department->addContact($contact);
        }
    }
}
