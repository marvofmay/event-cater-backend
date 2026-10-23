<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Infrastructure\Integration;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Kitchen\Application\Port\KitchenStaffDirectory;
use Doctrine\DBAL\Connection;

final readonly class KitchenEmployeeDirectory implements KitchenStaffDirectory
{
    public function __construct(private Connection $db)
    {
    }
    public function search(string $query): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
        return $this->db->fetchAllAssociative("SELECT uuid, CONCAT(first_name, ' ', last_name) AS name FROM employee WHERE active = 1 AND deleted_at IS NULL AND CONCAT(first_name, ' ', last_name) LIKE ? ESCAPE '!' ORDER BY last_name, first_name, uuid LIMIT 20", [$pattern]);
    }
    public function requireActive(string $uuid): string
    {
        $row = $this->db->fetchAssociative('SELECT first_name, last_name, active, deleted_at FROM employee WHERE uuid = ? FOR SHARE', [$uuid]);
        if (!$row || !$row['active'] || $row['deleted_at'] !== null) {
            throw new BusinessRuleViolation('Kitchen responsible employee must exist and be active.');
        }
        return $row['first_name'].' '.$row['last_name'];
    }
}
