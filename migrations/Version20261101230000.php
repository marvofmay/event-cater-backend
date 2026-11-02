<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant company_notices list/view to all roles; add company_document library tables and documents access';
    }

    public function up(Schema $schema): void
    {
        // Odczyt ogłoszeń dla wszystkich ról (admin i tak ma wszystko przez tenantAdministrator).
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT r.uuid, a.uuid, NOW(), NULL FROM role r CROSS JOIN access a WHERE r.deleted_at IS NULL AND a.name = 'company_notices' AND a.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'company_notices' JOIN permission p ON p.name IN ('list', 'view') WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");

        $this->addSql(<<<'SQL'
            CREATE TABLE company_document (
                uuid CHAR(36) NOT NULL,
                title VARCHAR(200) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                category VARCHAR(40) NOT NULL,
                stored_name VARCHAR(80) NOT NULL,
                file_name VARCHAR(150) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size INT NOT NULL,
                created_by_user_uuid CHAR(36) NOT NULL,
                created_by_name VARCHAR(250) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid),
                INDEX company_document_category (category),
                INDEX company_document_created (created_at),
                INDEX company_document_deleted (deleted_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);

        $this->addSql("INSERT INTO module (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), 'documents', 'Wspólna biblioteka dokumentów firmy', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'documents')");
        $this->addSql("UPDATE module SET deleted_at = NULL, active = 1, description = 'Wspólna biblioteka dokumentów firmy' WHERE name = 'documents'");
        $this->addSql("INSERT INTO access (uuid, module_uuid, name, description, active, created_at, deleted_at) SELECT UUID(), m.uuid, 'documents', 'Biblioteka dokumentów firmowych — odczyt i zarządzanie', 1, NOW(), NULL FROM module m WHERE m.name = 'documents' AND NOT EXISTS (SELECT 1 FROM access WHERE name = 'documents')");
        $this->addSql("UPDATE access SET deleted_at = NULL, active = 1, description = 'Biblioteka dokumentów firmowych — odczyt i zarządzanie' WHERE name = 'documents'");

        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT r.uuid, a.uuid, NOW(), NULL FROM role r CROSS JOIN access a WHERE r.deleted_at IS NULL AND a.name = 'documents' AND a.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'documents' JOIN permission p ON p.name IN ('list', 'view') WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_document');
    }
}
