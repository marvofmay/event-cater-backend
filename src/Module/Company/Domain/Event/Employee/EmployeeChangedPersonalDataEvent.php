<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Event\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\FirstName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\LastName;
use App\Module\System\Domain\ValueObject\UserUUID;

final readonly class EmployeeChangedPersonalDataEvent implements DomainEventInterface
{
    public function __construct(
        public EmployeeUUID $uuid,
        public FirstName $firstName,
        public LastName $lastName,
        public UserUUID $userUUID,
    ) {
    }
}
