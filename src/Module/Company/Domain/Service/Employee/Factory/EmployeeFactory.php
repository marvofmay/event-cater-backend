<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee\Factory;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Entity\Employee;

class EmployeeFactory
{
    public function create(DomainEventInterface $event): Employee
    {
        $employee = new Employee();
        $employee->setUUID($event->uuid->toString());

        $this->mapEventToEmployee($employee, $event);

        return $employee;
    }

    public function update(Employee $employee, DomainEventInterface $event): void
    {
        $this->mapEventToEmployee($employee, $event);
    }

    private function mapEventToEmployee(Employee $employee, DomainEventInterface $event): void
    {
        $employee->setFirstName($event->firstName->getValue());
        $employee->setLastName($event->lastName->getValue());
        $employee->setPESEL($event->pesel->getValue());
        $employee->setIsPeselFake($event->pesel->isFake());
        $employee->setEmploymentFrom(\DateTime::createFromFormat('Y-m-d', $event->employmentFrom->getValue()));
        if (null !== $event->employmentTo) {
            $employee->setEmploymentTo(\DateTime::createFromFormat('Y-m-d', $event->employmentTo->getValue()));
        }
        $employee->setInternalCode($event->internalCode);
        $employee->setExternalUUID($event->externalCode);
        $employee->setActive($event->active);
    }
}
