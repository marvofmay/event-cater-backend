<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Employee;

use App\Common\Domain\Interface\CommandInterface;

final readonly class ChangeEmployeePersonalDataCommand implements CommandInterface
{
    public function __construct(public string $firstName, public string $lastName)
    {
    }
}
