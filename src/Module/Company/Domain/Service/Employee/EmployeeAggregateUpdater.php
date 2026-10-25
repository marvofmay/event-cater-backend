<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Abstract\AggregateAbstract;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\ContractTypeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentFrom;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentTo;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\FirstName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\LastName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PESEL;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PositionUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\RoleUUID;
use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\System\Domain\ValueObject\UserUUID;

final class EmployeeAggregateUpdater extends AggregateAbstract
{
    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    private EntityManagerInterface $entityManager;

    public function update(array $row, ?EmployeeUUID $parentUUID, UserUUID $loggedUserUUID): void
    {
        $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
            EmployeeUUID::fromString($row[EmployeeImportColumnEnum::DYNAMIC_AGGREGATE_UUID->value])
        );

        $employeeAggregate->update(
            FirstName::fromString($row[EmployeeImportColumnEnum::FIRST_NAME->value]),
            LastName::fromString((string) ($row[EmployeeImportColumnEnum::LAST_NAME->value] ?? '')),
            PESEL::fromString((string) $row[EmployeeImportColumnEnum::PESEL->value], (bool) $row[EmployeeImportColumnEnum::IS_PESEL_FAKE->value]),
            EmploymentFrom::fromString($row[EmployeeImportColumnEnum::EMPLOYMENT_FROM->value]),
            CompanyUUID::fromString($this->entityManager->find(Department::class, $row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value])->getCompany()->getUUID()->toString()),
            DepartmentUUID::fromString($row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value]),
            PositionUUID::fromString($row[EmployeeImportColumnEnum::POSITION_UUID->value]),
            ContractTypeUUID::fromString($row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value]),
            RoleUUID::fromString($row[EmployeeImportColumnEnum::ROLE_UUID->value]),
            Emails::fromArray([$row[EmployeeImportColumnEnum::EMAIL->value]]),
            new Address(
                (string) ($row[EmployeeImportColumnEnum::STREET->value] ?? ''),
                (string) ($row[EmployeeImportColumnEnum::POSTCODE->value] ?? ''),
                (string) ($row[EmployeeImportColumnEnum::CITY->value] ?? ''),
                (string) ($row[EmployeeImportColumnEnum::COUNTRY->value] ?? '')
            ),
            $loggedUserUUID,
            $row[EmployeeImportColumnEnum::EXTERNAL_CODE->value],
            $row[EmployeeImportColumnEnum::INTERNAL_CODE->value],
            (bool) $row[EmployeeImportColumnEnum::ACTIVE->value],
            Phones::fromArray([(string) $row[EmployeeImportColumnEnum::PHONE->value]]),
            $parentUUID,
            $row[EmployeeImportColumnEnum::EMPLOYMENT_TO->value] ? EmploymentTo::fromString($row[EmployeeImportColumnEnum::EMPLOYMENT_TO->value], EmploymentFrom::fromString($row[EmployeeImportColumnEnum::EMPLOYMENT_FROM->value])) : null,
        );

        $this->commitEvents($employeeAggregate->pullEvents(), EmployeeAggregate::class);
    }
}
