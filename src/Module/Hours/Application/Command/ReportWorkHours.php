<?php

declare(strict_types=1);

namespace App\Module\Hours\Application\Command;

final readonly class ReportWorkHours
{
    public function __construct(
        public string $uuid,
        public string $userUuid,
        public string $employeeUuid,
        public string $employeeName,
        public string $date,
        public int $minutes,
        public string $comment,
        public string $kind,
        public ?string $eventUuid = null,
    ) {
    }
}
