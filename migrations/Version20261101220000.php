<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Company notices board for /info: tables, module, access and Pracownik list/view grants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE company_notice (
                uuid CHAR(36) NOT NULL,
                title VARCHAR(200) NOT NULL,
                body LONGTEXT NOT NULL,
                created_by_user_uuid CHAR(36) NOT NULL,
                created_by_name VARCHAR(250) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid),
                INDEX company_notice_created (created_at),
                INDEX company_notice_deleted (deleted_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
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
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);

        $this->addSql("INSERT INTO module (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), 'company_notices', 'Tablica ogłoszeń firmowych na stronie Informacje', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'company_notices')");
        $this->addSql("UPDATE module SET deleted_at = NULL, description = 'Tablica ogłoszeń firmowych na stronie Informacje', active = 1 WHERE name = 'company_notices'");
        $this->addSql("INSERT INTO access (uuid, module_uuid, name, description, active, created_at, deleted_at) SELECT UUID(), m.uuid, 'company_notices', 'Ogłoszenia firmowe — odczyt i zarządzanie tablicą informacji', 1, NOW(), NULL FROM module m WHERE m.name = 'company_notices' AND NOT EXISTS (SELECT 1 FROM access WHERE name = 'company_notices')");
        $this->addSql("UPDATE access SET deleted_at = NULL, description = 'Ogłoszenia firmowe — odczyt i zarządzanie tablicą informacji', active = 1 WHERE name = 'company_notices'");

        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT r.uuid, a.uuid, NOW(), NULL FROM role r CROSS JOIN access a WHERE r.name = 'Pracownik' AND r.deleted_at IS NULL AND a.name = 'company_notices' ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'company_notices' JOIN permission p ON p.name IN ('list', 'view') WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_notice_image');
        $this->addSql('DROP TABLE company_notice');
    }
}
