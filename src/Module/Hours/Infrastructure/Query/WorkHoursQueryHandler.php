<?php

declare(strict_types=1);

namespace App\Module\Hours\Infrastructure\Query;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Hours\Application\Query\ListEmployeeHours;
use App\Module\Hours\Application\Query\ListMyHours;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class WorkHoursQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function myHours(ListMyHours $query): array
    {
        [$from, $to] = $this->range($query->from, $query->to);
        $days = $this->daysForEmployee($query->employeeUuid, $from, $to);
        return [
            'from' => $from,
            'to' => $to,
            'employeeUuid' => $query->employeeUuid,
            'days' => $days,
            'totals' => $this->totals($days),
        ];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function employeeHours(ListEmployeeHours $query): array
    {
        [$from, $to] = $this->range($query->from, $query->to);
        $params = [$from, $to, $from, $to];
        $sql = <<<'SQL'
            SELECT e.uuid, TRIM(CONCAT(e.first_name, ' ', COALESCE(e.last_name, ''))) AS name
            FROM employee e
            WHERE e.deleted_at IS NULL AND e.active = 1
              AND (
                EXISTS (
                  SELECT 1 FROM event_staff_assignment sa
                  INNER JOIN catering_event ev ON ev.uuid = sa.event_uuid
                  WHERE sa.employee_uuid = e.uuid
                    AND ev.event_date BETWEEN ? AND ?
                    AND ev.status <> 'cancelled'
                )
                OR EXISTS (
                  SELECT 1 FROM work_hours_entry wh
                  WHERE wh.employee_uuid = e.uuid AND wh.work_date BETWEEN ? AND ?
                )
              )
        SQL;
        if ($query->employeeUuid !== null) {
            $sql .= ' AND e.uuid = ?';
            $params[] = $query->employeeUuid;
        }
        if ($query->search !== '') {
            $sql .= " AND (e.first_name LIKE ? ESCAPE '!' OR e.last_name LIKE ? ESCAPE '!' OR CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
            array_push($params, $pattern, $pattern, $pattern);
        }
        $sql .= ' ORDER BY e.last_name, e.first_name, e.uuid';
        $employees = $this->db->fetchAllAssociative($sql, $params);
        $items = [];
        foreach ($employees as $employee) {
            $days = $this->daysForEmployee($employee['uuid'], $from, $to);
            $items[] = [
                'employeeUuid' => $employee['uuid'],
                'employeeName' => $employee['name'],
                'days' => $days,
                'totals' => $this->totals($days),
            ];
        }
        return [
            'from' => $from,
            'to' => $to,
            'items' => $items,
            'totals' => $this->totals(array_merge(...array_map(static fn (array $item) => $item['days'], $items) ?: [[]])),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function daysForEmployee(string $employeeUuid, string $from, string $to): array
    {
        $assignments = $this->db->fetchAllAssociative(
            <<<'SQL'
                SELECT sa.uuid, sa.event_uuid, ev.title AS event_title, sa.starts_at, sa.ends_at,
                       ev.event_date AS work_date,
                       TIMESTAMPDIFF(MINUTE, sa.starts_at, sa.ends_at) AS minutes
                FROM event_staff_assignment sa
                INNER JOIN catering_event ev ON ev.uuid = sa.event_uuid
                WHERE sa.employee_uuid = ?
                  AND ev.event_date BETWEEN ? AND ?
                  AND ev.status <> 'cancelled'
                ORDER BY sa.starts_at, sa.uuid
            SQL,
            [$employeeUuid, $from, $to],
        );
        $entries = $this->db->fetchAllAssociative(
            <<<'SQL'
                SELECT uuid, employee_uuid, employee_name, work_date, minutes, comment, kind, event_uuid, created_at, updated_at
                FROM work_hours_entry
                WHERE employee_uuid = ? AND work_date BETWEEN ? AND ?
                ORDER BY work_date, created_at, uuid
            SQL,
            [$employeeUuid, $from, $to],
        );

        $days = [];
        foreach ($assignments as $row) {
            $date = $row['work_date'];
            $days[$date] ??= $this->emptyDay($date);
            $minutes = max(0, (int) $row['minutes']);
            $days[$date]['plannedMinutes'] += $minutes;
            $days[$date]['events'][] = [
                'assignmentUuid' => $row['uuid'],
                'eventUuid' => $row['event_uuid'],
                'eventTitle' => $row['event_title'],
                'startsAt' => (new \DateTimeImmutable($row['starts_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'endsAt' => (new \DateTimeImmutable($row['ends_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'minutes' => $minutes,
            ];
        }
        foreach ($entries as $row) {
            $date = $row['work_date'] instanceof \DateTimeInterface ? $row['work_date']->format('Y-m-d') : (string) $row['work_date'];
            $days[$date] ??= $this->emptyDay($date);
            $minutes = (int) $row['minutes'];
            $days[$date]['reportedMinutes'] += $minutes;
            $days[$date]['entries'][] = [
                'uuid' => $row['uuid'],
                'minutes' => $minutes,
                'comment' => $row['comment'],
                'kind' => $row['kind'],
                'eventUuid' => $row['event_uuid'],
                'createdAt' => (new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'updatedAt' => (new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
            ];
        }
        ksort($days);
        return array_values(array_map(static function (array $day): array {
            $day['effectiveMinutes'] = $day['reportedMinutes'] > 0 ? $day['reportedMinutes'] : $day['plannedMinutes'];
            $day['differenceMinutes'] = $day['reportedMinutes'] > 0 ? $day['reportedMinutes'] - $day['plannedMinutes'] : 0;
            return $day;
        }, $days));
    }

    /** @return array{date: string, plannedMinutes: int, reportedMinutes: int, effectiveMinutes: int, differenceMinutes: int, events: list<array<string, mixed>>, entries: list<array<string, mixed>>} */
    private function emptyDay(string $date): array
    {
        return [
            'date' => $date,
            'plannedMinutes' => 0,
            'reportedMinutes' => 0,
            'effectiveMinutes' => 0,
            'differenceMinutes' => 0,
            'events' => [],
            'entries' => [],
        ];
    }

    /** @param list<array<string, mixed>> $days */
    private function totals(array $days): array
    {
        $planned = 0;
        $reported = 0;
        $effective = 0;
        foreach ($days as $day) {
            $planned += (int) ($day['plannedMinutes'] ?? 0);
            $reported += (int) ($day['reportedMinutes'] ?? 0);
            $effective += (int) ($day['effectiveMinutes'] ?? 0);
        }
        return [
            'plannedMinutes' => $planned,
            'reportedMinutes' => $reported,
            'effectiveMinutes' => $effective,
            'differenceMinutes' => $reported > 0 ? $reported - $planned : 0,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function range(string $from, string $to): array
    {
        $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, new \DateTimeZone('UTC'));
        $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to, new \DateTimeZone('UTC'));
        if ($fromDate === false || $toDate === false || $fromDate > $toDate) {
            throw new BusinessRuleViolation('Zakres dat jest nieprawidłowy.');
        }
        if ($fromDate->diff($toDate)->days > 366) {
            throw new BusinessRuleViolation('Maksymalny zakres podglądu godzin to 366 dni.');
        }
        return [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')];
    }
}
