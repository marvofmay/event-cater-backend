<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\User;

use App\Module\Company\Application\ReadModel\Employee\EmployeeView;

final readonly class MeView
{
    public function __construct(
        public string $userUUID,
        public string $email,
        public ?EmployeeView $employee
    ) {
    }
}
