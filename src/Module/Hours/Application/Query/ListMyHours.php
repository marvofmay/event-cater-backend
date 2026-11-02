<?php

declare(strict_types=1);

namespace App\Module\Hours\Application\Query;

final readonly class ListMyHours
{
    public function __construct(
        public string $employeeUuid,
        public string $from,
        public string $to,
    ) {
    }
}
