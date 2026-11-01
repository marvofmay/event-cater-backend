<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair structure access and role grants created with legacy deleted_at defaults';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE access SET deleted_at = NULL WHERE name = 'company_structure' AND deleted_at = created_at");
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT DISTINCT ra.role_uuid, parent.uuid, NOW(), NULL FROM role_access ra JOIN access child ON child.uuid = ra.access_uuid JOIN access parent ON parent.name = 'manage' AND parent.module_uuid = child.module_uuid WHERE ra.deleted_at IS NULL AND child.name IN ('companies','departments','employees','industries','roles','positions','contract_types','imports') ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT DISTINCT ra.role_uuid, target.uuid, NOW(), NULL FROM role_access ra JOIN access old_access ON old_access.uuid = ra.access_uuid JOIN role_access_permission rap ON rap.role_uuid = ra.role_uuid AND rap.access_uuid = old_access.uuid AND rap.deleted_at IS NULL JOIN permission p ON p.uuid = rap.permission_uuid AND p.name = 'list' JOIN access target ON target.name = 'company_structure' WHERE ra.deleted_at IS NULL AND old_access.name = 'companies' ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'company_structure' JOIN permission p ON p.name = 'view' WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Cannot safely discard repaired role grants.');
    }
}
