<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\EmploymentView;

use App\Module\Company\Domain\Entity\Employee;

final readonly class EmploymentView
{
    public function __construct(
        public string $position,
        public string $contractType,
        public string $role,
        public string $employmentFrom,
        public ?string $employmentTo,
        public ?string $parentEmployee
    ) {
    }

    public static function fromEmployee(Employee $employee): self
    {
        $parent = $employee->getParentEmployee();

        return new self(
            position: $employee->getPosition()->getName(),
            contractType: $employee->getContractType()->getName(),
            role: $employee->getRole()->getName(),
            employmentFrom: $employee->getEmploymentFrom()->format('Y-m-d'),
            employmentTo: $employee->getEmploymentTo()?->format('Y-m-d'),
            parentEmployee: $parent
                ? $parent->getLastName() . ' ' . $parent->getFirstName()
                : null
        );
    }
}
