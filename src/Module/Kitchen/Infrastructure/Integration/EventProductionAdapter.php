<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Infrastructure\Integration;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Kitchen\Application\Port\EventProductionSource;
use Doctrine\DBAL\Connection;

final readonly class EventProductionAdapter implements EventProductionSource
{
    public function __construct(private Connection $db)
    {
    }
    public function get(string $eventUuid, bool $lock = false): array
    {
        $row = $this->db->fetchAssociative('SELECT uuid, number, title, event_date, timezone, ready_at, guest_count, menu, location, version, status FROM catering_event WHERE uuid = ?'.($lock ? ' FOR UPDATE' : ''), [$eventUuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Event not found.');
        }
        return $this->map($row);
    }
    public function getMany(array $eventUuids): array
    {
        if ($eventUuids === []) {
            return [];
        }
        $rows = $this->db->fetchAllAssociative('SELECT uuid, number, title, event_date, timezone, ready_at, guest_count, menu, location, version, status FROM catering_event WHERE uuid IN (?)', [$eventUuids], [\Doctrine\DBAL\ArrayParameterType::STRING]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['uuid']] = $this->map($row);
        }
        return $result;
    }
    private function map(array $row): array
    {
        return ['eventUuid' => $row['uuid'], 'number' => $row['number'], 'title' => $row['title'], 'date' => $row['event_date'],
            'timezone' => $row['timezone'], 'readyAt' => $row['ready_at'] === null ? null : (new \DateTimeImmutable($row['ready_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'location' => json_decode($row['location'], true, flags: JSON_THROW_ON_ERROR), 'guestCount' => $row['guest_count'] === null ? null : (int) $row['guest_count'], 'menu' => json_decode($row['menu'], true, flags: JSON_THROW_ON_ERROR),
            'eventVersion' => (int) $row['version'], 'eventStatus' => $row['status']];
    }
}
