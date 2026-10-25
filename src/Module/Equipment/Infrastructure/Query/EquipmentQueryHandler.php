<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Equipment\Application\Query\GetEquipmentItem;
use App\Module\Equipment\Application\Query\ListEquipmentItems;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEquipmentItem $query): array
    {
        $row = $this->db->fetchAssociative('SELECT item.*, category.name AS category, storage_location.name AS storage_location_name FROM equipment_item item JOIN equipment_category category ON category.uuid = item.category_uuid LEFT JOIN equipment_location storage_location ON storage_location.uuid = item.storage_location_uuid WHERE item.uuid = ?', [$query->uuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Equipment not found.');
        }
        return $this->withPhotos([$this->normalize($row)])[0];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListEquipmentItems $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($query->active !== null) {
            $where[] = 'item.active = ?';
            $params[] = (int) $query->active;
        }
        if ($query->category !== null) {
            $where[] = '(item.category_uuid = ? OR category.name = ?)';
            array_push($params, $query->category, $query->category);
        }
        if ($query->search !== '') {
            $where[] = "(item.name LIKE ? ESCAPE '!' OR item.code LIKE ? ESCAPE '!' OR item.slang_name LIKE ? ESCAPE '!' OR storage_location.name LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
            array_push($params, $pattern, $pattern, $pattern, $pattern);
        }
        if ($query->storageLocationUuid !== null) {
            $where[] = 'item.storage_location_uuid = ?';
            $params[] = $query->storageLocationUuid;
        }
        $condition = implode(' AND ', $where);
        $from = ' FROM equipment_item item JOIN equipment_category category ON category.uuid = item.category_uuid LEFT JOIN equipment_location storage_location ON storage_location.uuid = item.storage_location_uuid WHERE ';
        $total = (int) $this->db->fetchOne('SELECT COUNT(*)'.$from.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT item.*, category.name AS category, storage_location.name AS storage_location_name'.$from.$condition.' ORDER BY item.name, item.uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        return $query->page->result($this->withPhotos(array_map($this->normalize(...), $rows)), $total);
    }

    private function withPhotos(array $items): array
    {
        if ($items === []) {
            return [];
        }
        $photos = $this->db->fetchAllAssociative('SELECT uuid, equipment_uuid, name, mime_type, size, created_at FROM equipment_photo WHERE equipment_uuid IN (?) ORDER BY created_at, uuid', [array_column($items, 'uuid')], [ArrayParameterType::STRING]);
        $byItem = [];
        foreach ($photos as $photo) {
            $byItem[$photo['equipment_uuid']][] = [
                'uuid' => $photo['uuid'], 'name' => $photo['name'], 'mimeType' => $photo['mime_type'],
                'size' => (int) $photo['size'], 'createdAt' => (new \DateTimeImmutable($photo['created_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
            ];
        }
        foreach ($items as &$item) {
            $item['photos'] = $byItem[$item['uuid']] ?? [];
        }
        return $items;
    }

    private function normalize(array $row): array
    {
        return ['uuid' => $row['uuid'], 'code' => $row['code'], 'name' => $row['name'], 'category' => $row['category'],
            'categoryUuid' => $row['category_uuid'], 'slangName' => $row['slang_name'], 'stockQuantity' => (int) $row['stock_quantity'],
            'storageLocationUuid' => $row['storage_location_uuid'], 'storageLocation' => $row['storage_location_name'],
            'unit' => $row['unit'], 'description' => $row['description'], 'active' => (bool) $row['active'], 'version' => (int) $row['version'], 'updatedAt' => (new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM)];
    }
}
