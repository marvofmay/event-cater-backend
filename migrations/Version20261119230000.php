<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Copied tenant databases can have the notices migration recorded
 * without the tables, or the original table without publication columns.
 * /info then fails while reading company_notice.
 */
final class Version20261119230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure the company notice board tables exist with publication columns';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasTable('company_notice')) {
            $this->connection->executeStatement(<<<'SQL'
                CREATE TABLE company_notice (
                    uuid CHAR(36) NOT NULL,
                    title VARCHAR(200) NOT NULL,
                    body LONGTEXT NOT NULL,
                    created_by_user_uuid CHAR(36) NOT NULL,
                    created_by_name VARCHAR(250) NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    publish_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    archived_at DATETIME DEFAULT NULL,
                    deleted_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (uuid),
                    INDEX company_notice_created (created_at),
                    INDEX company_notice_deleted (deleted_at),
                    INDEX company_notice_publication (active, publish_at, archived_at)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        } else {
            if (!$this->hasColumn('company_notice', 'publish_at')) {
                $this->connection->executeStatement("ALTER TABLE company_notice ADD publish_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
            if (!$this->hasColumn('company_notice', 'active')) {
                $this->connection->executeStatement('ALTER TABLE company_notice ADD active TINYINT(1) NOT NULL DEFAULT 1');
            }
            if (!$this->hasColumn('company_notice', 'archived_at')) {
                $this->connection->executeStatement('ALTER TABLE company_notice ADD archived_at DATETIME DEFAULT NULL');
            }
            if (!$this->hasIndex('company_notice', 'company_notice_publication')) {
                $this->connection->executeStatement('ALTER TABLE company_notice ADD INDEX company_notice_publication (active, publish_at, archived_at)');
            }
        }

        if (!$this->hasTable('company_notice_image')) {
            $this->connection->executeStatement(<<<'SQL'
                CREATE TABLE company_notice_image (
                    uuid CHAR(36) NOT NULL,
                    notice_uuid CHAR(36) NOT NULL,
                    stored_name VARCHAR(80) NOT NULL,
                    file_name VARCHAR(150) NOT NULL,
                    mime_type VARCHAR(64) NOT NULL,
                    size INT NOT NULL,
                    role VARCHAR(16) NOT NULL,
                    sort_order INT NOT NULL,
                    PRIMARY KEY (uuid),
                    INDEX company_notice_image_notice (notice_uuid),
                    CONSTRAINT FK_company_notice_image_notice FOREIGN KEY (notice_uuid) REFERENCES company_notice (uuid) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        }
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

    private function hasIndex(string $table, string $index): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index],
        ) > 0;
    }
}
