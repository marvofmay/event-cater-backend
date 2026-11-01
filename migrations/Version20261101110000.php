<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenant-scoped direct messages, recipient read receipts, and read permissions for existing messaging roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE direct_message (uuid VARCHAR(36) NOT NULL, sender_uuid VARCHAR(36) NOT NULL, sender_name VARCHAR(300) NOT NULL, title VARCHAR(200) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX direct_message_sender_date (sender_uuid, created_at), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE direct_message_receipt (message_uuid VARCHAR(36) NOT NULL, user_uuid VARCHAR(36) NOT NULL, recipient_name VARCHAR(300) NOT NULL, read_at DATETIME DEFAULT NULL, INDEX direct_message_user_unread (user_uuid, read_at, message_uuid), PRIMARY KEY(message_uuid, user_uuid), CONSTRAINT direct_message_receipt_message FOREIGN KEY (message_uuid) REFERENCES direct_message (uuid) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, permission.uuid, NOW(), NULL FROM role_access ra JOIN access ON access.uuid = ra.access_uuid AND access.name = 'messages' AND access.deleted_at IS NULL JOIN module ON module.uuid = access.module_uuid AND module.name = 'system' JOIN permission ON permission.name IN ('list', 'view') AND permission.active = 1 AND permission.deleted_at IS NULL WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE direct_message_receipt');
        $this->addSql('DROP TABLE direct_message');
    }
}
