<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Company;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Entity\Industry;
use App\Module\Company\Domain\Interface\Address\AddressWriterInterface;
use App\Module\Company\Domain\Interface\Company\CompanyReaderInterface;
use App\Module\Company\Domain\Interface\Company\CompanyWriterInterface;
use App\Module\Company\Domain\Interface\Contact\ContactWriterInterface;
use App\Module\Company\Domain\Interface\Industry\IndustryReaderInterface;
use App\Module\Company\Domain\Service\Company\Factory\CompanyFactory;
use App\Module\Company\Domain\Service\Factory\AddressFactory;
use App\Module\Company\Domain\Service\Factory\ContactFactory;
use Doctrine\Common\Collections\Collection;

final readonly class CompanyUpdater
{
    public function __construct(
        private CompanyFactory $companyFactory,
        private AddressFactory $addressFactory,
        private ContactFactory $contactFactory,
        private CompanyWriterInterface $companyWriterRepository,
        private CompanyReaderInterface $companyReaderRepository,
        private IndustryReaderInterface $industryReaderRepository,
        private ContactWriterInterface $contactWriterRepository,
        private AddressWriterInterface $addressWriterRepository,
        private EntityReferenceCache $entityReferenceCache,
    ) {
    }

    public function update(DomainEventInterface $event): void
    {
        $company = $this->companyReaderRepository->getCompanyByUUID($event->uuid->toString());

        $address = $company->getAddress();
        $contacts = $company->getContacts();

        $this->companyFactory->update($company, $event);

        $this->deleteAddress($address);
        $this->deleteContacts($contacts);

        $address = $this->addressFactory->create($event->address);
        $company->setAddress($address);

        $contacts = $this->contactFactory->create($event->phones, $event->emails, $event->websites);
        foreach ($contacts as $contact) {
            $company->addContact($contact);
        }

        $industry = $this->entityReferenceCache->get(
            Industry::class,
            $event->industryUUID->toString(),
            fn (string $uuid) => $this->industryReaderRepository->getIndustryByUUID($uuid)
        );

        $parentCompany = $event->parentCompanyUUID?->toString()
            ? $this->entityReferenceCache->get(
                Company::class,
                $event->parentCompanyUUID->toString(),
                fn (string $uuid) => $this->companyReaderRepository->getCompanyByUUID($uuid)
            )
            : null;

        $this->setCompanyRelations($company, $industry, $parentCompany, $address, $contacts);

        $this->companyWriterRepository->saveCompanyInDB($company);
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

    private function setCompanyRelations(Company $company, Industry $industry, ?Company $parentCompany, Address $address, array $contacts): void
    {
        $company->setIndustry($industry);
        $company->setAddress($address);
        $company->setParentCompany($parentCompany);

        foreach ($contacts as $contact) {
            $company->addContact($contact);
        }
    }
}
