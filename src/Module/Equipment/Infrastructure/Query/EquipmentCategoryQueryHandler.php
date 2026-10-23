<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Equipment\Application\Query\GetEquipmentCategory;
use App\Module\Equipment\Application\Query\ListEquipmentCategories;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentCategoryQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEquipmentCategory $query): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM equipment_category WHERE uuid = ?', [$query->uuid]);
        if (!$row) {
            throw new PlanningResourceNotFound('Nie znaleziono kategorii sprzętu.');
        }
        return $this->normalize($row);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListEquipmentCategories $query): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM equipment_category WHERE name LIKE ? ESCAPE '!'", [$pattern]);
        $rows = $this->db->fetchAllAssociative("SELECT * FROM equipment_category WHERE name LIKE ? ESCAPE '!' ORDER BY name, uuid LIMIT ".$query->page->limit.' OFFSET '.$query->page->offset(), [$pattern]);
        return $query->page->result(array_map($this->normalize(...), $rows), $total);
    }

    private function normalize(array $row): array
    {
        return ['uuid' => $row['uuid'], 'name' => $row['name'], 'version' => (int) $row['version']];
    }
}
