<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class AssignEventStaff
{
    public function __construct(public string $uuid, public int $version, public string $employeeUuid, public array $functions, public \DateTimeImmutable $startsAt, public \DateTimeImmutable $endsAt, public ?string $notes, public string $actorUuid)
    {
    }
}
