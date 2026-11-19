<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order event-store rows that share the same second by their microseconds';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$this->liveTableExists() || $this->liveColumnExists()) {
            return;
        }

        $this->addSql('ALTER TABLE event_store ADD created_at_us INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        if (!$this->liveColumnExists()) {
            return;
        }

        $this->addSql('ALTER TABLE event_store DROP created_at_us');
    }

    private function liveTableExists(): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['event_store'],
        );

        return false !== $found && null !== $found;
    }

    private function liveColumnExists(): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['event_store', 'created_at_us'],
        );

        return false !== $found && null !== $found;
    }
}
