<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Direct message replies and independent inbox/sent deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE direct_message ADD reply_to_uuid VARCHAR(36) DEFAULT NULL, ADD sender_deleted_at DATETIME DEFAULT NULL, DROP INDEX direct_message_sender_date, ADD INDEX direct_message_sender_date (sender_uuid, sender_deleted_at, created_at)');
        $this->addSql('ALTER TABLE direct_message_receipt ADD deleted_at DATETIME DEFAULT NULL, DROP INDEX direct_message_user_unread, ADD INDEX direct_message_user_unread (user_uuid, deleted_at, read_at, message_uuid)');
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, permission.uuid, NOW(), NULL FROM role_access ra JOIN access ON access.uuid = ra.access_uuid AND access.name = 'messages' AND access.deleted_at IS NULL JOIN module ON module.uuid = access.module_uuid AND module.name = 'system' JOIN permission ON permission.name = 'delete' AND permission.active = 1 AND permission.deleted_at IS NULL WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE direct_message_receipt DROP INDEX direct_message_user_unread, DROP deleted_at, ADD INDEX direct_message_user_unread (user_uuid, read_at, message_uuid)');
        $this->addSql('ALTER TABLE direct_message DROP INDEX direct_message_sender_date, DROP reply_to_uuid, DROP sender_deleted_at, ADD INDEX direct_message_sender_date (sender_uuid, created_at)');
    }
}
