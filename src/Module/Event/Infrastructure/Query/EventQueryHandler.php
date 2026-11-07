<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Company\Infrastructure\ReadModel\EmployeeAvatarReader;
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
    public function __construct(
        private Connection $db,
        private EntityManagerInterface $em,
        private PreparationQueryHandler $preparation,
        private \App\Common\Infrastructure\Query\HistoryActors $historyActors,
        private EmployeeAvatarReader $avatars,
    ) {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEvent $query): array
    {
        $row = $this->requireEvent($query->uuid);
        $data = $this->eventRow($row);
        $staffRows = $this->db->fetchAllAssociative(
            "SELECT sa.*, account.uuid AS user_uuid, account.login_phone,
                (SELECT contact.data FROM contact
                    WHERE contact.employee_uuid = sa.employee_uuid
                      AND contact.type = 'phone'
                      AND contact.deleted_at IS NULL
                      AND contact.active = 1
                    ORDER BY contact.created_at
                    LIMIT 1) AS contact_phone
             FROM event_staff_assignment sa
             LEFT JOIN user account ON account.employee_uuid = sa.employee_uuid AND account.deleted_at IS NULL
             WHERE sa.event_uuid = ?
             ORDER BY sa.employee_name, sa.uuid",
            [$query->uuid]
        );
        $avatars = $this->avatars->forEmployees(array_column($staffRows, 'employee_uuid'));
        $data['staff'] = array_map(static function (array $r) use ($avatars): array {
            $phone = $r['login_phone'] ?: $r['contact_phone'];

            return [
                'uuid' => $r['uuid'],
                'employeeUuid' => $r['employee_uuid'],
                'employeeName' => $r['employee_name'],
                'functions' => json_decode($r['functions'], true, flags: JSON_THROW_ON_ERROR),
                'startsAt' => self::dateTime($r['starts_at']),
                'endsAt' => self::dateTime($r['ends_at']),
                'notes' => $r['notes'],
                'userUuid' => $r['user_uuid'],
                'phone' => $phone !== null && $phone !== '' ? (string) $phone : null,
                'avatar' => $avatars[$r['employee_uuid']] ?? null,
            ];
        }, $staffRows);
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
        if ($query->staffEmployeeUuid !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM event_staff_assignment sa WHERE sa.event_uuid = catering_event.uuid AND sa.employee_uuid = ?)';
            $params[] = $query->staffEmployeeUuid;
        }
        if ($query->packerEmployeeUuid !== null) {
            $phaseSql = '';
            $phaseParams = [];
            if ($query->preparationPhase === 'pending') {
                $phaseSql = ' AND pl.status IN (?, ?)';
                $phaseParams = ['approved', 'in_preparation'];
            } elseif ($query->preparationPhase === 'confirmed') {
                $phaseSql = ' AND pl.status IN (?, ?)';
                $phaseParams = ['ready_for_loading', 'loaded'];
            }
            $where[] = 'EXISTS (SELECT 1 FROM event_preparation_list pl WHERE pl.event_uuid = catering_event.uuid AND pl.responsible_uuid = ?'.$phaseSql.')';
            $params[] = $query->packerEmployeeUuid;
            array_push($params, ...$phaseParams);
        }
        $condition = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM catering_event WHERE '.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT uuid, number, title, event_date, timezone, starts_at, ends_at, status, guest_count, version FROM catering_event WHERE '.$condition.' ORDER BY event_date, uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        $items = array_map(static fn (array $r) => [
            'uuid' => $r['uuid'], 'number' => $r['number'], 'title' => $r['title'], 'date' => $r['event_date'],
            'timezone' => $r['timezone'], 'startsAt' => self::dateTime($r['starts_at']), 'endsAt' => self::dateTime($r['ends_at']),
            'status' => $r['status'], 'guestCount' => $r['guest_count'] === null ? null : (int) $r['guest_count'], 'version' => (int) $r['version'],
        ], $rows);
        if ($query->viewerEmployeeUuid !== null && $items !== []) {
            $items = $this->attachInvolvement($items, $query->viewerEmployeeUuid);
        }
        if ($query->packerEmployeeUuid !== null && $items !== []) {
            $items = $this->attachPreparationStatus($items);
        }
        return $query->page->result($items, $total);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function attachInvolvement(array $items, string $employeeUuid): array
    {
        $uuids = array_column($items, 'uuid');
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));

        $staffRows = $this->db->fetchAllAssociative(
            'SELECT event_uuid, functions FROM event_staff_assignment WHERE employee_uuid = ? AND event_uuid IN ('.$placeholders.')',
            [$employeeUuid, ...$uuids],
        );
        $staffByEvent = [];
        foreach ($staffRows as $row) {
            $staffByEvent[$row['event_uuid']] = json_decode((string) $row['functions'], true, flags: JSON_THROW_ON_ERROR);
        }

        $packerRows = $this->db->fetchFirstColumn(
            'SELECT event_uuid FROM event_preparation_list WHERE responsible_uuid = ? AND event_uuid IN ('.$placeholders.')',
            [$employeeUuid, ...$uuids],
        );
        $packerSet = array_fill_keys($packerRows, true);

        return array_map(static function (array $item) use ($staffByEvent, $packerSet): array {
            $functions = $staffByEvent[$item['uuid']] ?? [];
            $item['involvement'] = [
                'onStaff' => array_key_exists($item['uuid'], $staffByEvent),
                'isPacker' => isset($packerSet[$item['uuid']]),
                'myFunctions' => is_array($functions) ? $functions : [],
            ];

            return $item;
        }, $items);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function attachPreparationStatus(array $items): array
    {
        $uuids = array_column($items, 'uuid');
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $rows = $this->db->fetchAllAssociative(
            'SELECT event_uuid, status FROM event_preparation_list WHERE event_uuid IN ('.$placeholders.')',
            $uuids,
        );
        $byEvent = [];
        foreach ($rows as $row) {
            $byEvent[(string) $row['event_uuid']] = (string) $row['status'];
        }

        return array_map(static function (array $item) use ($byEvent): array {
            $item['preparationStatus'] = $byEvent[$item['uuid']] ?? null;

            return $item;
        }, $items);
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
        return $query->page->result($this->historyActors->enrich(array_map(static fn (EventHistoryEntry $entry) => $entry->toArray(), $rows)), $total);
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
        $row = $this->db->fetchAssociative("SELECT event.*, CASE WHEN coordinator.uuid IS NULL THEN NULL ELSE TRIM(CONCAT_WS(' ', coordinator.first_name, coordinator.last_name)) END AS coordinator_name FROM catering_event event LEFT JOIN employee coordinator ON coordinator.uuid = event.coordinator_uuid AND coordinator.deleted_at IS NULL WHERE event.uuid = ?", [$uuid]);
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
            'coordinatorName' => $r['coordinator_name'],
            'customerName' => $r['customer_name'], 'customerUuid' => $r['customer_uuid'], 'contacts' => json_decode($r['contacts'], true, flags: JSON_THROW_ON_ERROR),
            'menu' => json_decode($r['menu'], true, flags: JSON_THROW_ON_ERROR), 'version' => (int) $r['version'],
            'createdAt' => self::dateTime($r['created_at']), 'updatedAt' => self::dateTime($r['updated_at'])];
    }

    private static function dateTime(?string $value): ?string
    {
        return $value === null ? null : (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
