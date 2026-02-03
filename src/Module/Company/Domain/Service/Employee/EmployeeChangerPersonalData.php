<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;

final readonly class EmployeeChangerPersonalData
{
    public function __construct(
        private EmployeeWriterInterface $employeeWriterRepository,
        private EmployeeReaderInterface $employeeReaderRepository,
    ) {
    }
    public function change(DomainEventInterface $event): void
    {
        $employee = $this->employeeReaderRepository->getEmployeeByUUID($event->uuid->toString());
        $employee->setFirstName($event->firstName->getValue());
        $employee->setLastName($event->lastName->getValue());
        $this->employeeWriterRepository->saveEmployee($employee);
    }
}
