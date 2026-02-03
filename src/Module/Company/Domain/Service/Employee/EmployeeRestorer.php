<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;

final readonly class EmployeeRestorer
{
    public function __construct(
        private EmployeeWriterInterface $employeeWriterRepository,
        private EmployeeReaderInterface $employeeReaderRepository,
    ) {
    }

    public function restore(DomainEventInterface $event): void
    {
        $employee = $this->employeeReaderRepository->getDeletedEmployeeByUUID($event->uuid->toString());
        $employee->deletedAt = null;
        // $employee->setUpdatedAt($now);

        $address = $this->employeeReaderRepository->getDeletedAddressByEmployeeByUUID($event->uuid->toString());
        if ($address) {
            $address->deletedAt = null;
        }

        $contacts = $this->employeeReaderRepository->getDeletedContactsByEmployeeByUUID($event->uuid->toString());
        foreach ($contacts as $contact) {
            $contact->setDeletedAt(null);
        }

        $user = $this->employeeReaderRepository->getDeletedUserByEmployeeUUID($event->uuid->toString());
        if ($user) {
            $user->deletedAt = null;
        }

        $this->employeeWriterRepository->saveEmployee($employee);
    }
}
