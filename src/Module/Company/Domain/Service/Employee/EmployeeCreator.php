<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\ContractType;
use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Interface\Company\CompanyReaderInterface;
use App\Module\Company\Domain\Interface\ContractType\ContractTypeReaderInterface;
use App\Module\Company\Domain\Interface\Department\DepartmentReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;
use App\Module\Company\Domain\Interface\Position\PositionReaderInterface;
use App\Module\Company\Domain\Interface\Role\RoleReaderInterface;
use App\Module\Company\Domain\Service\Employee\Factory\EmployeeFactory;
use App\Module\Company\Domain\Service\Factory\AddressFactory;
use App\Module\Company\Domain\Service\Factory\ContactFactory;
use App\Module\Company\Domain\Service\User\UserFactory;

final readonly class EmployeeCreator
{
    public function __construct(
        private EmployeeFactory $employeeFactory,
        private AddressFactory $addressFactory,
        private ContactFactory $contactFactory,
        private EmployeeWriterInterface $employeeWriterRepository,
        private CompanyReaderInterface $companyReaderRepository,
        private DepartmentReaderInterface $departmentReaderRepository,
        private EmployeeReaderInterface $employeeReaderRepository,
        private ContractTypeReaderInterface $contractTypeReaderRepository,
        private PositionReaderInterface $positionReaderRepository,
        private RoleReaderInterface $roleReaderRepository,
        private UserFactory $userFactory,
        private EntityReferenceCache $entityReferenceCache,
    ) {
    }

    public function create(DomainEventInterface $event): void
    {
        $employee = $this->employeeFactory->create($event);

        $email = $event->emails->toArray()[0];
        $user = $this->userFactory->create($email, $email);

        $company = $this->entityReferenceCache->get(
            Company::class,
            $event->companyUUID->toString(),
            fn (string $uuid) => $this->companyReaderRepository->getCompanyByUUID($uuid)
        );

        $department = $this->entityReferenceCache->get(
            Department::class,
            $event->departmentUUID->toString(),
            fn (string $uuid) => $this->departmentReaderRepository->getDepartmentByUUID($uuid)
        );

        $role = $this->entityReferenceCache->get(
            Role::class,
            $event->roleUUID->toString(),
            fn (string $uuid) => $this->roleReaderRepository->getRoleByUUID($uuid)
        );

        $position = $this->entityReferenceCache->get(
            Position::class,
            $event->positionUUID->toString(),
            fn (string $uuid) => $this->positionReaderRepository->getPositionByUUID($uuid)
        );

        $contractType = $this->entityReferenceCache->get(
            ContractType::class,
            $event->contractTypeUUID->toString(),
            fn (string $uuid) => $this->contractTypeReaderRepository->getContractTypeByUUID($uuid)
        );

        $parentEmployee = $event->parentEmployeeUUID?->toString()
            ? $this->entityReferenceCache->get(
                Employee::class,
                $event->parentEmployeeUUID->toString(),
                fn (string $uuid) => $this->employeeReaderRepository->getEmployeeByUUID($uuid)
            )
            : null;

        $address = $this->addressFactory->create($event->address);
        $contacts = $this->contactFactory->create($event->phones, $event->emails);

        $this->setEmployeeRelations($employee, $company, $department, $role, $position, $contractType, $parentEmployee, $address, $contacts, $user);

        $this->employeeWriterRepository->saveEmployee($employee);
    }

    private function setEmployeeRelations(
        Employee $employee,
        Company $company,
        Department $department,
        Role $role,
        Position $position,
        ContractType $contractType,
        ?Employee $parentEmployee,
        Address $address,
        array $contacts,
        User $user,
    ): void {
        $employee->setCompany($company);
        $employee->setDepartment($department);
        $employee->setRole($role);
        $employee->setPosition($position);
        $employee->setContractType($contractType);
        $employee->setParentEmployee($parentEmployee);
        $employee->setAddress($address);
        $employee->setUser($user);

        foreach ($contacts as $contact) {
            $employee->addContact($contact);
        }
    }
}
