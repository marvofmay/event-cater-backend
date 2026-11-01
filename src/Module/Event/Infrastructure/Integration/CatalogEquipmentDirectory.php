<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Integration;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\EquipmentCatalog;
use Doctrine\DBAL\Connection;

final readonly class CatalogEquipmentDirectory implements EquipmentCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    public function requireActive(string $uuid): array
    {
        $item = $this->connection->fetchAssociative('SELECT item.uuid, item.name, item.code, item.unit, item.active, location.name AS storageLocation FROM equipment_item item LEFT JOIN equipment_location location ON location.uuid = item.storage_location_uuid WHERE item.uuid = ? FOR SHARE', [$uuid]);
        if (!$item || !$item['active']) {
            throw new BusinessRuleViolation('Equipment must exist and be active.');
        }
        unset($item['active']);
        return $item;
    }
}
