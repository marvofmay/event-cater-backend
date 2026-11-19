<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MySQL keeps NOT NULL when a column is changed with only DEFAULT NULL.
 * Version20261119120000 shipped that statement, and a later conditional
 * migration can be skipped when it runs inside a transaction around DDL.
 * This pass is not transactional and repeats the nullability fix.
 */
final class Version20261119180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop NOT NULL on equipment code columns so items can be saved without a code';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            $column = $this->codeColumn($table);
            if ($column === null || ($column['IS_NULLABLE'] ?? null) !== 'NO') {
                continue;
            }
            $this->addSql(sprintf(
                'ALTER TABLE %s MODIFY code %s%s NULL DEFAULT NULL',
                $table,
                $this->columnType($column),
                $this->collation($column),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            $column = $this->codeColumn($table);
            if ($column === null || ($column['IS_NULLABLE'] ?? null) !== 'YES') {
                continue;
            }
            $this->addSql(sprintf(
                "UPDATE %s SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL",
                $table,
            ));
            $this->addSql(sprintf(
                'ALTER TABLE %s MODIFY code %s%s NOT NULL',
                $table,
                $this->columnType($column),
                $this->collation($column),
            ));
        }
    }

    /** @return array<string, mixed>|null */
    private function codeColumn(string $table): ?array
    {
        $column = $this->connection->fetchAssociative(
            'SELECT IS_NULLABLE, COLUMN_TYPE, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'code'],
        );

        return is_array($column) ? $column : null;
    }

    /** @param array<string, mixed> $column */
    private function columnType(array $column): string
    {
        $type = $column['COLUMN_TYPE'] ?? '';

        return is_string($type) && preg_match('/^varchar\([0-9]+\)$/i', $type) === 1 ? $type : 'varchar(50)';
    }

    /** @param array<string, mixed> $column */
    private function collation(array $column): string
    {
        $collation = $column['COLLATION_NAME'] ?? '';

        return is_string($collation) && preg_match('/^[A-Za-z0-9_]+$/', $collation) === 1 ? ' COLLATE '.$collation : '';
    }
}
