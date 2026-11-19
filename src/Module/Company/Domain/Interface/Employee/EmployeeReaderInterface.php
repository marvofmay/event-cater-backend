<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Interface\Employee;

use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\User;
use Doctrine\Common\Collections\Collection;

interface EmployeeReaderInterface
{
    public function getEmployeeByUUID(string $uuid): ?Employee;

    public function getEmployeesByUUID(array $selectedUUID): Collection;

    public function getEmployeeByEmail(string $email, ?string $uuid = null): ?User;

    public function getEmployeeByPESEL(string $pesel, ?string $uuid = null): ?Employee;

    public function getEmployeesByPESEL(array $selectedPESEL): Collection;

    public function isEmployeeWithUUIDExists(string $uuid): bool;

    public function isEmployeeWithEmailExists(string $email, ?string $uuid = null): bool;

    public function isEmployeeContactEmailTaken(string $email, ?string $uuid = null): bool;

    public function isEmployeeWithPESELExists(string $pesel, ?string $uuid = null): bool;

    public function isEmployeeAlreadyExistsWithEmailOrPESEL(string $pesel, string $email, ?string $uuid): bool;

    public function getDeletedEmployeeByUUID(string $uuid): ?Employee;

    public function getDeletedAddressByEmployeeByUUID(string $uuid): ?Address;

    public function getDeletedContactsByEmployeeByUUID(string $uuid): Collection;

    public function getDeletedUserByEmployeeUUID(string $uuid): ?User;

    public function getEmployeesPESELByEmails(array $emails): Collection;
    public function getAvailableParentEmployeeOptions(string $companyUUID, ?string $employeeUUID = null, ?string $departmentUUID = null): array;
}
