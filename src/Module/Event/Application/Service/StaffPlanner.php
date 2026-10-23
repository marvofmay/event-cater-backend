<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Domain\Entity\CateringEvent;
use Doctrine\DBAL\Connection;

final readonly class StaffPlanner
{
    public function __construct(private Connection $connection, private EmployeeDirectory $employees)
    {
    }

    public function assertAvailable(string $employeeUuid, string $exceptEvent, \DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $utc = new \DateTimeZone('UTC');
        $count = $this->connection->fetchOne(
            "SELECT a.uuid FROM event_staff_assignment a INNER JOIN catering_event e ON e.uuid = a.event_uuid WHERE a.employee_uuid = ? AND a.event_uuid <> ? AND e.status NOT IN ('cancelled', 'completed') AND a.starts_at < ? AND a.ends_at > ? LIMIT 1 FOR UPDATE",
            [$employeeUuid, $exceptEvent, $to->setTimezone($utc)->format('Y-m-d H:i:s'), $from->setTimezone($utc)->format('Y-m-d H:i:s')]
        );
        if ($count !== false) {
            throw new PlanningConflict('Employee is already assigned to an overlapping event.');
        }
    }

    public function validateTeam(CateringEvent $event): void
    {
        $assignments = $this->connection->fetchAllAssociative('SELECT employee_uuid, starts_at, ends_at FROM event_staff_assignment WHERE event_uuid = ? ORDER BY employee_uuid FOR UPDATE', [$event->uuid()]);
        $ids = array_column($assignments, 'employee_uuid');
        if ($event->coordinatorUuid()) {
            $ids[] = $event->coordinatorUuid();
        }
        $ids = array_unique($ids);
        sort($ids);
        foreach ($ids as $id) {
            $this->employees->requireActive($id);
        }
        foreach ($assignments as $assignment) {
            $from = new \DateTimeImmutable($assignment['starts_at'], new \DateTimeZone('UTC'));
            $to = new \DateTimeImmutable($assignment['ends_at'], new \DateTimeZone('UTC'));
            $zone = new \DateTimeZone($event->schedule()->timezone);
            $this->employees->requireActive($assignment['employee_uuid'], $from->setTimezone($zone), $to->setTimezone($zone));
            $this->assertAvailable($assignment['employee_uuid'], $event->uuid(), $from, $to);
        }
    }
}
