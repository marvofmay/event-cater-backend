<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Separate company structure access from management and preserve existing role grants'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO access (uuid, module_uuid, name, description, active, created_at) SELECT UUID(), uuid, 'company_structure', 'Podgląd struktury firmy i danych kontaktowych pracowników', 1, NOW() FROM module WHERE name = 'company' AND NOT EXISTS (SELECT 1 FROM access WHERE name = 'company_structure')");
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at) SELECT DISTINCT ra.role_uuid, parent.uuid, NOW() FROM role_access ra JOIN access child ON child.uuid = ra.access_uuid JOIN access parent ON parent.name = 'manage' AND parent.module_uuid = child.module_uuid WHERE ra.deleted_at IS NULL AND child.name IN ('companies','departments','employees','industries','roles','positions','contract_types','imports') ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at) SELECT DISTINCT ra.role_uuid, target.uuid, NOW() FROM role_access ra JOIN access old_access ON old_access.uuid = ra.access_uuid JOIN role_access_permission rap ON rap.role_uuid = ra.role_uuid AND rap.access_uuid = old_access.uuid AND rap.deleted_at IS NULL JOIN permission p ON p.uuid = rap.permission_uuid AND p.name = 'list' JOIN access target ON target.name = 'company_structure' WHERE ra.deleted_at IS NULL AND old_access.name = 'companies' ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW() FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'company_structure' JOIN permission p ON p.name = 'view' WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Role access hierarchy migration cannot safely discard grants.'); }
}
