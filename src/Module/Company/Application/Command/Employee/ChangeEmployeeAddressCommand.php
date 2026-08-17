<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Employee;

use App\Common\Application\DTO\AddressDTO;
use App\Common\Domain\Interface\CommandInterface;

class ChangeEmployeeAddressCommand implements CommandInterface
{
    public function __construct(public AddressDTO $address)
    {
    }
}
