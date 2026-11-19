<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creating an event writes creation_notice_sent_at. A copied company
 * database can have the earlier migration recorded without that column,
 * and the first event form then fails on save.
 */
final class Version20261119240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure catering events have the creation notice timestamp';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasTable('catering_event')) {
            return;
        }
        if (!$this->hasColumn('catering_event', 'creation_notice_sent_at')) {
            $this->connection->executeStatement('ALTER TABLE catering_event ADD creation_notice_sent_at DATETIME DEFAULT NULL');
        }
        $this->connection->executeStatement('UPDATE catering_event SET creation_notice_sent_at = created_at WHERE creation_notice_sent_at IS NULL');
    }

    public function down(Schema $schema): void
    {
    }

    private function hasTable(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        ) > 0;
    }
}
