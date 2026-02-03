<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Interface\Employee;

use App\Module\Company\Domain\Entity\Employee;
use Doctrine\Common\Collections\Collection;

interface EmployeeWriterInterface
{
    public function saveEmployee(Employee $employee): void;

    public function saveEmployeesInDB(Collection $employees): void;

    public function deleteEmployeeInDB(Employee $employee): void;

    public function deleteMultipleEmployeesInDB(Collection $employees): void;
}
