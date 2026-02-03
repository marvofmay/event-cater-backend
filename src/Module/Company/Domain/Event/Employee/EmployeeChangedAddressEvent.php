<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Event\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use App\Module\System\Domain\ValueObject\UserUUID;

final readonly class EmployeeChangedAddressEvent implements DomainEventInterface
{
    public function __construct(
        public EmployeeUUID $uuid,
        public Address $address,
        public UserUUID $userUUID,
    ) {
    }
}
