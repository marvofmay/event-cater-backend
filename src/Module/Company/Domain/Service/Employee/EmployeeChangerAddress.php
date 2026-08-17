<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Interface\Address\AddressWriterInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;
use App\Module\Company\Domain\Service\Factory\AddressFactory;

final readonly class EmployeeChangerAddress
{
    public function __construct(
        private EmployeeWriterInterface $employeeWriterRepository,
        private EmployeeReaderInterface $employeeReaderRepository,
        private AddressWriterInterface $addressWriterRepository,
        private AddressFactory $addressFactory,
    ) {
    }
    public function change(DomainEventInterface $event): void
    {
        $employee = $this->employeeReaderRepository->getEmployeeByUUID($event->uuid->toString());
        $address = $employee->getAddress();

        $this->deleteAddress($address);
        $address = $this->addressFactory->create($event->address);
        $this->setEmployeeAddressRelation($employee, $address);

        $this->employeeWriterRepository->saveEmployee($employee);
    }

    private function deleteAddress(?Address $address): void
    {
        if (null !== $address) {
            $this->addressWriterRepository->deleteAddressInDB($address, Address::HARD_DELETED_AT);
        }
    }

    private function setEmployeeAddressRelation(Employee $employee, Address $address): void
    {
        $employee->setAddress($address);
    }
}
