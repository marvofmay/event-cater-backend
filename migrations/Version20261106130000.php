<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split manage_event_media into view/add/delete event media permissions';
    }

    public function up(Schema $schema): void
    {
        foreach ([
            ['view_event_media', 'Przeglądanie zdjęć i filmów z wydarzeń'],
            ['add_event_media', 'Dodawanie zdjęć i filmów do wydarzeń'],
            ['delete_event_media', 'Usuwanie zdjęć i filmów z wydarzeń'],
        ] as [$name, $description]) {
            $this->addSql("INSERT INTO permission (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), '{$name}', '{$description}', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = '{$name}')");
            $this->addSql("UPDATE permission SET deleted_at = NULL, description = '{$description}', active = 1 WHERE name = '{$name}'");
        }

        foreach (['view_event_media', 'add_event_media', 'delete_event_media'] as $name) {
            $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at)
                SELECT rap.role_uuid, rap.access_uuid, p_new.uuid, NOW(), NULL
                FROM role_access_permission rap
                JOIN access a ON a.uuid = rap.access_uuid AND a.name = 'events' AND a.deleted_at IS NULL
                JOIN permission p_old ON p_old.uuid = rap.permission_uuid AND p_old.name = 'manage_event_media'
                JOIN permission p_new ON p_new.name = '{$name}'
                WHERE rap.deleted_at IS NULL
                ON DUPLICATE KEY UPDATE deleted_at = NULL");
        }

        $this->addSql("UPDATE role_access_permission rap
            JOIN permission p ON p.uuid = rap.permission_uuid AND p.name = 'manage_event_media'
            SET rap.deleted_at = NOW()
            WHERE rap.deleted_at IS NULL");

        $this->addSql("UPDATE permission SET deleted_at = NOW(), active = 0 WHERE name = 'manage_event_media' AND deleted_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE permission SET deleted_at = NULL, active = 1, description = 'Zarządzanie materiałami z wydarzeń' WHERE name = 'manage_event_media'");

        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at)
            SELECT DISTINCT rap.role_uuid, rap.access_uuid, p_old.uuid, NOW(), NULL
            FROM role_access_permission rap
            JOIN access a ON a.uuid = rap.access_uuid AND a.name = 'events' AND a.deleted_at IS NULL
            JOIN permission p_new ON p_new.uuid = rap.permission_uuid AND p_new.name IN ('view_event_media', 'add_event_media', 'delete_event_media')
            JOIN permission p_old ON p_old.name = 'manage_event_media'
            WHERE rap.deleted_at IS NULL
            ON DUPLICATE KEY UPDATE deleted_at = NULL");

        foreach (['view_event_media', 'add_event_media', 'delete_event_media'] as $name) {
            $this->addSql("UPDATE role_access_permission rap
                JOIN permission p ON p.uuid = rap.permission_uuid AND p.name = '{$name}'
                SET rap.deleted_at = NOW()
                WHERE rap.deleted_at IS NULL");
            $this->addSql("UPDATE permission SET deleted_at = NOW(), active = 0 WHERE name = '{$name}'");
        }
    }
}
