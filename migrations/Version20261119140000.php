<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make equipment code columns nullable when an earlier change left them required';
    }

    public function up(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            if ($this->codeNullability($table) !== 'NO') {
                continue;
            }
            $this->addSql(sprintf('ALTER TABLE %s MODIFY code VARCHAR(50) NULL DEFAULT NULL', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            if ($this->codeNullability($table) !== 'YES') {
                continue;
            }
            $this->addSql(sprintf("UPDATE %s SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL", $table));
            $this->addSql(sprintf('ALTER TABLE %s MODIFY code VARCHAR(50) NOT NULL', $table));
        }
    }

    private function codeNullability(string $table): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'code'],
        );

        return is_string($value) ? $value : null;
    }
}
