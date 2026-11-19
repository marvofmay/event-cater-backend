<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20261116153000 adds created_at_us only when the schema object
 * passed into up() does not already list the column. A tenant database
 * copied from an older backup can have that migration recorded while the
 * column is still missing, and every company write then fails.
 */
final class Version20261119200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_store.created_at_us when a tenant database is still missing it';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($this->hasColumn('event_store', 'created_at_us')) {
            return;
        }

        if (!$this->hasTable('event_store')) {
            return;
        }

        $this->addSql('ALTER TABLE event_store ADD created_at_us INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        if (!$this->hasColumn('event_store', 'created_at_us')) {
            return;
        }

        $this->addSql('ALTER TABLE event_store DROP created_at_us');
    }

    private function hasTable(string $table): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        return false !== $found && null !== $found;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return false !== $found && null !== $found;
    }
}
