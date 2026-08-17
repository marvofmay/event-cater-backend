<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Event\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Common\Domain\Interface\NotifiableEventInterface;

final readonly class EmployeeImportedEvent implements DomainEventInterface, NotifiableEventInterface
{
    public function __construct(public array $rows, public string $importUUID)
    {
    }
}
