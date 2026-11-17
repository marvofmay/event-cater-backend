<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Support tickets for the SaaS owner, stored only in the control-plane database';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['saas_tenant']) || $this->connection->createSchemaManager()->tablesExist(['support_ticket'])) {
            return;
        }

        $this->addSql("CREATE TABLE support_ticket (
            uuid CHAR(36) NOT NULL PRIMARY KEY,
            tenant_uuid CHAR(36) NOT NULL,
            tenant_slug VARCHAR(63) NOT NULL,
            tenant_name VARCHAR(200) NOT NULL,
            author_uuid CHAR(36) NOT NULL,
            author_email VARCHAR(180) NOT NULL,
            author_name VARCHAR(200) NOT NULL,
            category VARCHAR(32) NOT NULL,
            subject VARCHAR(160) NOT NULL,
            status VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX support_ticket_author (tenant_uuid, author_uuid, created_at),
            INDEX support_ticket_status (status, updated_at),
            INDEX support_ticket_tenant (tenant_uuid, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addSql("CREATE TABLE support_message (
            uuid CHAR(36) NOT NULL PRIMARY KEY,
            ticket_uuid CHAR(36) NOT NULL,
            author_role VARCHAR(16) NOT NULL,
            author_uuid CHAR(36) NOT NULL,
            author_label VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX support_message_ticket (ticket_uuid, created_at),
            CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_uuid) REFERENCES support_ticket (uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addSql("CREATE TABLE support_attachment (
            uuid CHAR(36) NOT NULL PRIMARY KEY,
            ticket_uuid CHAR(36) NOT NULL,
            message_uuid CHAR(36) NOT NULL,
            original_name VARCHAR(180) NOT NULL,
            mime_type VARCHAR(64) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            storage_key CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX support_attachment_key (storage_key),
            INDEX support_attachment_ticket (ticket_uuid),
            CONSTRAINT fk_support_attachment_ticket FOREIGN KEY (ticket_uuid) REFERENCES support_ticket (uuid),
            CONSTRAINT fk_support_attachment_message FOREIGN KEY (message_uuid) REFERENCES support_message (uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['support_ticket'])) {
            return;
        }
        $this->addSql('DROP TABLE support_attachment');
        $this->addSql('DROP TABLE support_message');
        $this->addSql('DROP TABLE support_ticket');
    }
}
