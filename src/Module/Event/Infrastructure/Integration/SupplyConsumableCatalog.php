<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Integration;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\ConsumableCatalog;
use Doctrine\DBAL\Connection;

final readonly class SupplyConsumableCatalog implements ConsumableCatalog
{
    public function __construct(private Connection $db)
    {
    }
    public function requireActive(string $uuid): array
    {
        $row = $this->db->fetchAssociative('SELECT uuid, name, code, unit, active FROM consumable_item WHERE uuid = ? FOR SHARE', [$uuid]);
        if (!$row || !$row['active']) {
            throw new BusinessRuleViolation('Consumable must exist and be active.');
        }
        unset($row['active']);
        return $row;
    }
}
