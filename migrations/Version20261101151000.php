<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore soft-deleted my_hours grants for the Pracownik role';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT r.uuid, a.uuid, NOW(), NULL FROM role r CROSS JOIN access a WHERE r.name = 'Pracownik' AND r.deleted_at IS NULL AND a.name = 'my_hours' ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'my_hours' JOIN permission p ON p.name IN ('list', 'view', 'create', 'update', 'delete') WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restored role grants should not be discarded automatically.');
    }
}
