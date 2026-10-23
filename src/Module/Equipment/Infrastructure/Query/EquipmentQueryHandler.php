<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Equipment\Application\Query\GetEquipmentItem;
use App\Module\Equipment\Application\Query\ListEquipmentItems;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEquipmentItem $query): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM equipment_item WHERE uuid = ?', [$query->uuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Equipment not found.');
        }
        return $this->normalize($row);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListEquipmentItems $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($query->active !== null) {
            $where[] = 'active = ?';
            $params[] = (int) $query->active;
        }
        if ($query->category !== null) {
            $where[] = 'category = ?';
            $params[] = $query->category;
        }
        if ($query->search !== '') {
            $where[] = "(name LIKE ? ESCAPE '!' OR code LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
            array_push($params, $pattern, $pattern);
        }
        $condition = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM equipment_item WHERE '.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT * FROM equipment_item WHERE '.$condition.' ORDER BY name, uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        return $query->page->result(array_map($this->normalize(...), $rows), $total);
    }

    private function normalize(array $row): array
    {
        return ['uuid' => $row['uuid'], 'code' => $row['code'], 'name' => $row['name'], 'category' => $row['category'],
            'unit' => $row['unit'], 'description' => $row['description'], 'active' => (bool) $row['active'], 'version' => (int) $row['version'], 'updatedAt' => (new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM)];
    }
}
