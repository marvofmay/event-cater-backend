<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Enum\Contact\ContactTypeEnum;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\Company\Domain\Interface\ContractType\ContractTypeReaderInterface;
use App\Module\Company\Domain\Interface\Department\DepartmentReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Position\PositionReaderInterface;
use App\Module\Company\Domain\Interface\Role\RoleReaderInterface;

final class ImportEmployeesReferenceLoader
{
    public array $duplicateLoginPhones = [];
    public array $duplicateLoginEmails = [];
    public array $supervisorRows = [];
    public array $departments = [] {
        get {
            return $this->departments;
        }
    }
    public array $positions = [] {
        get {
            return $this->positions;
        }
    }
    public array $roles = [] {
        get {
            return $this->roles;
        }
    }
    public array $employees = [] {
        get {
            return $this->employees;
        }
    }
    public array $contractTypes = [] {
        get {
            return $this->contractTypes;
        }
    }
    public array $emailsPESELs = [] {
        get {
            return $this->emailsPESELs;
        }
    }

    public function __construct(
        private readonly DepartmentReaderInterface $departmentReaderRepository,
        private readonly PositionReaderInterface $positionReaderRepository,
        private readonly RoleReaderInterface $roleReaderRepository,
        private readonly EmployeeReaderInterface $employeeReaderRepository,
        private readonly ContractTypeReaderInterface $contractTypeReaderRepository,
        private readonly EntityReferenceCache $entityReferenceCache,
    ) {
    }

    public function preload(array $rows): void
    {
        $departmentUUIDs = [];
        $positionUUIDs = [];
        $roleUUIDs = [];
        $contractTypeUUIDs = [];
        $employeePESELs = [];
        $employeeEmails = [];
        $phoneOwners = [];
        $emailOwners = [];
        $this->duplicateLoginEmails = [];
        $this->supervisorRows = [];
        $this->duplicateLoginPhones = [];

        foreach ($rows as $row) {
            $this->supervisorRows[(string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? '')] = [
                'parent' => trim((string) ($row[EmployeeImportColumnEnum::PARENT_EMPLOYEE_PESEL->value] ?? '')),
                'department' => (string) ($row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value] ?? ''),
            ];
            $email = strtolower(trim((string) ($row[EmployeeImportColumnEnum::EMAIL->value] ?? '')));
            $ownerPesel = (string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? '');
            if ($email !== '') {
                if (isset($emailOwners[$email]) && $emailOwners[$email] !== $ownerPesel) $this->duplicateLoginEmails[$email] = true;
                $emailOwners[$email] = $ownerPesel;
            }
            try {
                $phone = \App\Module\Company\Domain\Service\User\LoginPhone::normalize((string) ($row[EmployeeImportColumnEnum::PHONE->value] ?? ''));
                $pesel = (string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? '');
                if (isset($phoneOwners[$phone]) && $phoneOwners[$phone] !== $pesel) {
                    $this->duplicateLoginPhones[$phone] = true;
                }
                $phoneOwners[$phone] = $pesel;
            } catch (\InvalidArgumentException) {
            }
            if (!empty($row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value])) {
                $departmentUUIDs[] = (string) $row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::POSITION_UUID->value])) {
                $positionUUIDs[] = (string) $row[EmployeeImportColumnEnum::POSITION_UUID->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::ROLE_UUID->value])) {
                $roleUUIDs[] = (string) $row[EmployeeImportColumnEnum::ROLE_UUID->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value])) {
                $contractTypeUUIDs[] = (string) $row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::PESEL->value])) {
                $employeePESELs[] = (string) $row[EmployeeImportColumnEnum::PESEL->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::PARENT_EMPLOYEE_PESEL->value])) {
                $employeePESELs[] = (string) $row[EmployeeImportColumnEnum::PARENT_EMPLOYEE_PESEL->value];
            }
            if (!empty($row[EmployeeImportColumnEnum::EMAIL->value])) {
                $employeeEmails[] = (string) $row[EmployeeImportColumnEnum::EMAIL->value];
            }
        }

        $departmentUUIDs = array_unique($departmentUUIDs);
        $positionUUIDs = array_unique($positionUUIDs);
        $roleUUIDs = array_unique($roleUUIDs);
        $contractTypeUUIDs = array_unique($contractTypeUUIDs);
        $employeePESELs = array_unique($employeePESELs);
        $employeeEmails = array_unique($employeeEmails);

        $this->departments = $this->mapByUUID($this->departmentReaderRepository->getDepartmentsByUUID($departmentUUIDs));
        $this->positions = $this->mapByUUID($this->positionReaderRepository->getPositionsByUUID($positionUUIDs));
        $this->roles = $this->mapByUUID($this->roleReaderRepository->getRolesByUUIDs($roleUUIDs));
        $this->contractTypes = $this->mapByUUID($this->contractTypeReaderRepository->getContractTypesByUUIDs($contractTypeUUIDs));
        $this->employees = $this->mapByPESEL($this->employeeReaderRepository->getEmployeesByPESEL($employeePESELs));
        $this->emailsPESELs = $this->mapByEmail($this->employeeReaderRepository->getEmployeesPESELByEmails($employeeEmails));
    }

    private function mapByUUID(iterable $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getUUID()->toString()] = $entity;
            $this->entityReferenceCache->set($entity);
        }

        return $map;
    }

    private function mapByPESEL(iterable $employees): array
    {
        $map = [];
        foreach ($employees as $employee) {
            $map[$employee->getPESEL()] = $employee;
            $this->entityReferenceCache->set($employee);
        }

        return $map;
    }

    private function mapByEmail(iterable $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $map[$item[ContactTypeEnum::EMAIL->value]] = $item[Employee::COLUMN_PESEL];
        }

        return $map;
    }
}
