<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Department;

use App\Common\Domain\Abstract\AggregateAbstract;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Aggregate\Department\DepartmentAggregate;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\Name;
use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Aggregate\ValueObject\Websites;
use App\Module\Company\Domain\Enum\DepartmentImportColumnEnum;
use App\Module\System\Domain\ValueObject\UserUUID;

final class DepartmentAggregateUpdater extends AggregateAbstract
{
    public function update(array $row, ?DepartmentUUID $parentUUID, UserUUID $loggedUserUUID): void
    {
        $phone = trim((string) ($row[DepartmentImportColumnEnum::PHONE->value] ?? ''));
        $email = trim((string) ($row[DepartmentImportColumnEnum::EMAIL->value] ?? ''));
        $departmentAggregate = $this->departmentAggregateReaderRepository->getDepartmentAggregateByUUID(
            DepartmentUUID::fromString($row[DepartmentImportColumnEnum::DYNAMIC_AGGREGATE_UUID->value]),
        );

        $departmentAggregate->update(
            CompanyUUID::fromString($row[DepartmentImportColumnEnum::COMPANY_UUID->value]),
            Name::fromString($row[DepartmentImportColumnEnum::DEPARTMENT_NAME->value]),
            $row[DepartmentImportColumnEnum::DEPARTMENT_INTERNAL_CODE->value],
            new Address(
                $row[DepartmentImportColumnEnum::STREET->value],
                $row[DepartmentImportColumnEnum::POSTCODE->value],
                $row[DepartmentImportColumnEnum::CITY->value],
                $row[DepartmentImportColumnEnum::COUNTRY->value]
            ),
            $loggedUserUUID,
            (bool) $row[DepartmentImportColumnEnum::ACTIVE->value],
            $row[DepartmentImportColumnEnum::DEPARTMENT_DESCRIPTION->value],
            $phone !== '' ? Phones::fromArray([$phone]) : null,
            $email !== '' ? Emails::fromArray([$email]) : null,
            $row[DepartmentImportColumnEnum::WEBSITE->value] ? Websites::fromArray([$row[DepartmentImportColumnEnum::WEBSITE->value]]) : null,
            $parentUUID,
        );

        $this->commitEvents($departmentAggregate->pullEvents(), DepartmentAggregate::class);
    }
}
