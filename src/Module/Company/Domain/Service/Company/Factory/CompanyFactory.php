<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Company\Factory;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Entity\Company;

class CompanyFactory
{
    public function create(DomainEventInterface $event): Company
    {
        $company = new Company();
        $company->setUUID($event->uuid->toString());

        $this->mapEventToCompany($company, $event);

        return $company;
    }

    public function update(Company $company, DomainEventInterface $event): void
    {
        $this->mapEventToCompany($company, $event);
    }

    private function mapEventToCompany(Company $company, DomainEventInterface $event): void
    {
        $company->setFullName($event->fullName->getValue());
        $company->setShortName($event->shortName?->getValue());
        $company->setInternalCode($event->internalCode);
        $company->setNIP($event->nip->getValue());
        $company->setREGON($event->regon->getValue());
        $company->setDescription($event->description);
        $company->setActive($event->active);
    }
}
