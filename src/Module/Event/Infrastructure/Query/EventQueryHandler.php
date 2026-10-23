<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Query\GetEvent;
use App\Module\Event\Application\Query\GetEventHistory;
use App\Module\Event\Application\Query\GetEventMedia;
use App\Module\Event\Application\Query\ListEvents;
use App\Module\Event\Application\Query\SearchEventEmployees;
use App\Module\Event\Domain\Entity\EventHistoryEntry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EventQueryHandler
{
    public function __construct(private Connection $db, private EntityManagerInterface $em, private PreparationQueryHandler $preparation)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEvent $query): array
    {
        $row = $this->requireEvent($query->uuid);
        $data = $this->eventRow($row);
        $data['staff'] = array_map(static fn (array $r) => [
            'uuid' => $r['uuid'], 'employeeUuid' => $r['employee_uuid'], 'employeeName' => $r['employee_name'],
            'functions' => json_decode($r['functions'], true, flags: JSON_THROW_ON_ERROR),
            'startsAt' => self::dateTime($r['starts_at']), 'endsAt' => self::dateTime($r['ends_at']), 'notes' => $r['notes'],
        ], $this->db->fetchAllAssociative('SELECT * FROM event_staff_assignment WHERE event_uuid = ? ORDER BY employee_name, uuid', [$query->uuid]));
        $data['equipment'] = array_map(static fn (array $r) => [
            'uuid' => $r['uuid'], 'equipmentUuid' => $r['equipment_uuid'], 'name' => $r['name'], 'code' => $r['code'],
            'unit' => $r['unit'], 'quantity' => (int) $r['quantity'], 'notes' => $r['notes'],
        ], $this->db->fetchAllAssociative('SELECT * FROM event_equipment_requirement WHERE event_uuid = ? ORDER BY name, uuid', [$query->uuid]));
        $data['attachments'] = array_map(static fn (array $r) => [
            'uuid' => $r['uuid'], 'fileUuid' => $r['file_uuid'], 'name' => $r['file_name'],
            'mimeType' => $r['mime_type'], 'size' => (int) $r['size'], 'authorUuid' => $r['author_uuid'],
            'description' => $r['description'], 'createdAt' => self::dateTime($r['created_at']),
        ], $this->db->fetchAllAssociative('SELECT a.*, f.file_name FROM event_attachment a INNER JOIN file f ON f.uuid = a.file_uuid AND f.deleted_at IS NULL WHERE a.event_uuid = ? ORDER BY a.created_at, a.uuid', [$query->uuid]));
        $data['preparation'] = $this->preparation->get(new \App\Module\Event\Application\Query\GetEventPreparation($query->uuid));
        unset($data['preparation']['lines']);
        return $data;
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListEvents $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if ($query->$field !== null) {
                $where[] = 'event_date '.$operator.' ?';
                $params[] = $query->$field;
            }
        }
        if ($query->status !== null) {
            $where[] = 'status = ?';
            $params[] = $query->status;
        }
        if ($query->search !== '') {
            $where[] = "(title LIKE ? ESCAPE '!' OR number LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
            array_push($params, $pattern, $pattern);
        }
        $condition = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM catering_event WHERE '.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT uuid, number, title, event_date, timezone, starts_at, ends_at, status, guest_count, version FROM catering_event WHERE '.$condition.' ORDER BY event_date, uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        $items = array_map(static fn (array $r) => [
            'uuid' => $r['uuid'], 'number' => $r['number'], 'title' => $r['title'], 'date' => $r['event_date'],
            'timezone' => $r['timezone'], 'startsAt' => self::dateTime($r['starts_at']), 'endsAt' => self::dateTime($r['ends_at']),
            'status' => $r['status'], 'guestCount' => $r['guest_count'] === null ? null : (int) $r['guest_count'], 'version' => (int) $r['version'],
        ], $rows);
        return $query->page->result($items, $total);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function history(GetEventHistory $query): array
    {
        $this->requireEvent($query->uuid);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM event_history WHERE event_uuid = ?', [$query->uuid]);
        $rows = $this->em->getRepository(EventHistoryEntry::class)->findBy(
            ['event' => $query->uuid],
            ['revision' => 'DESC'],
            $query->page->limit,
            $query->page->offset()
        );
        return $query->page->result(array_map(static fn (EventHistoryEntry $entry) => $entry->toArray(), $rows), $total);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function employees(SearchEventEmployees $query): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
        return $this->db->fetchAllAssociative("SELECT uuid, CONCAT(first_name, ' ', last_name) AS name, internal_code AS internalCode FROM employee WHERE active = 1 AND deleted_at IS NULL AND (CONCAT(first_name, ' ', last_name) LIKE ? ESCAPE '!' OR internal_code LIKE ? ESCAPE '!') ORDER BY last_name, first_name, uuid LIMIT 20", [$pattern, $pattern]);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function media(GetEventMedia $query): array
    {
        $row = $this->db->fetchAssociative('SELECT f.file_name, f.file_path, a.mime_type FROM event_attachment a INNER JOIN file f ON f.uuid = a.file_uuid AND f.deleted_at IS NULL WHERE a.event_uuid = ? AND a.uuid = ?', [$query->eventUuid, $query->attachmentUuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Attachment not found.');
        }
        return $row;
    }

    private function requireEvent(string $uuid): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM catering_event WHERE uuid = ?', [$uuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Event not found.');
        }
        return $row;
    }

    private function eventRow(array $r): array
    {
        return ['uuid' => $r['uuid'], 'number' => $r['number'], 'title' => $r['title'], 'description' => $r['description'],
            'status' => $r['status'], 'date' => $r['event_date'], 'timezone' => $r['timezone'],
            'readyAt' => self::dateTime($r['ready_at']), 'startsAt' => self::dateTime($r['starts_at']), 'endsAt' => self::dateTime($r['ends_at']),
            'location' => json_decode($r['location'], true, flags: JSON_THROW_ON_ERROR),
            'guestCount' => $r['guest_count'] === null ? null : (int) $r['guest_count'], 'coordinatorUuid' => $r['coordinator_uuid'],
            'customerName' => $r['customer_name'], 'contacts' => json_decode($r['contacts'], true, flags: JSON_THROW_ON_ERROR),
            'menu' => json_decode($r['menu'], true, flags: JSON_THROW_ON_ERROR), 'version' => (int) $r['version'],
            'createdAt' => self::dateTime($r['created_at']), 'updatedAt' => self::dateTime($r['updated_at'])];
    }

    private static function dateTime(?string $value): ?string
    {
        return $value === null ? null : (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
