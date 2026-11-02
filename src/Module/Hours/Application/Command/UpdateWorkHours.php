<?php

declare(strict_types=1);

namespace App\Module\Hours\Application\Command;

final readonly class UpdateWorkHours
{
    public function __construct(
        public string $uuid,
        public string $employeeUuid,
        public int $minutes,
        public string $comment,
    ) {
    }
}
