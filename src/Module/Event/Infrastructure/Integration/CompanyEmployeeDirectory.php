<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Integration;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\EmployeeDirectory;
use Doctrine\DBAL\Connection;

final readonly class CompanyEmployeeDirectory implements EmployeeDirectory
{
    public function __construct(private Connection $connection)
    {
    }

    public function requireActive(string $uuid, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        $employee = $this->connection->fetchAssociative(
            'SELECT first_name, last_name, active, deleted_at, employment_from, employment_to FROM employee WHERE uuid = ? FOR UPDATE',
            [$uuid]
        );
        if (!$employee || !$employee['active'] || $employee['deleted_at'] !== null) {
            throw new BusinessRuleViolation('Employee must exist and be active.');
        }
        if (($from && $employee['employment_from'] && $from->format('Y-m-d') < substr($employee['employment_from'], 0, 10))
            || ($to && $employee['employment_to'] && $to->format('Y-m-d') > substr($employee['employment_to'], 0, 10))) {
            throw new BusinessRuleViolation('Assignment falls outside employee employment dates.');
        }
        return $employee['first_name'].' '.$employee['last_name'];
    }
}
