<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Private tenant file manager, folders, storage revision and read permissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE managed_file (uuid CHAR(36) NOT NULL, parent_uuid CHAR(36) DEFAULT NULL, name VARCHAR(150) NOT NULL, name_key CHAR(64) NOT NULL, kind VARCHAR(10) NOT NULL, blob_name VARCHAR(80) DEFAULT NULL, mime_type VARCHAR(150) DEFAULT NULL, size BIGINT NOT NULL DEFAULT 0, version INT NOT NULL DEFAULT 1, created_by CHAR(36) DEFAULT NULL, updated_by CHAR(36) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (uuid), UNIQUE INDEX managed_file_sibling (parent_uuid, name_key), INDEX managed_file_blob (blob_name), CONSTRAINT managed_file_parent FOREIGN KEY (parent_uuid) REFERENCES managed_file (uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("INSERT INTO managed_file (uuid, name, name_key, kind, created_at, updated_at) VALUES ('00000000-0000-0000-0000-000000000000', '', '', 'folder', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, permission.uuid, NOW(), NULL FROM role_access ra JOIN access ON access.uuid = ra.access_uuid AND access.name = 'file_manager' AND access.deleted_at IS NULL JOIN module ON module.uuid = access.module_uuid AND module.name = 'documents' JOIN permission ON permission.name IN ('list', 'view') AND permission.active = 1 AND permission.deleted_at IS NULL WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE managed_file');
    }
}
