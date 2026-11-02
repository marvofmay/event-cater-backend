<?php

declare(strict_types=1);

namespace App\Module\Hours\Application\Query;

final readonly class ListEmployeeHours
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $employeeUuid = null,
        public string $search = '',
    ) {
    }
}
