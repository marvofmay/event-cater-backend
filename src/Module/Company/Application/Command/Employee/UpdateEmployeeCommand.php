<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Employee;

use App\Common\Application\DTO\AddressDTO;
use App\Common\Domain\Interface\CommandInterface;

class UpdateEmployeeCommand implements CommandInterface
{
    public function __construct(
        public string $employeeUUID,
        public string $companyUUID,
        public string $departmentUUID,
        public string $positionUUID,
        public string $contractTypeUUID,
        public string $roleUUID,
        public ?string $parentEmployeeUUID,
        public ?string $externalCode,
        public ?string $internalCode,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $pesel,
        public string $employmentFrom,
        public ?string $employmentTo,
        public bool $active,
        public ?array $phones,
        public AddressDTO $address,
    ) {
    }
}
