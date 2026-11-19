<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Earlier migrations could be recorded without dropping NOT NULL:
 * a DEFAULT NULL change keeps the column required, and a later check
 * can skip the repair. Saving equipment without a code then fails.
 * This statement is idempotent when the column is already nullable.
 */
final class Version20261119210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force equipment code columns to accept an empty code';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $this->addSql(sprintf('ALTER TABLE %s MODIFY code VARCHAR(50) NULL DEFAULT NULL', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['equipment_item', 'event_equipment_requirement', 'event_preparation_line'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $this->addSql(sprintf(
                "UPDATE %s SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL",
                $table,
            ));
            $this->addSql(sprintf('ALTER TABLE %s MODIFY code VARCHAR(50) NOT NULL', $table));
        }
    }

    private function hasTable(string $table): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        return false !== $found && null !== $found;
    }
}
