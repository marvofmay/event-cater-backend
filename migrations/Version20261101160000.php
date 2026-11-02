<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facebook page connection, post log and publish_facebook permission';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE facebook_page_connection (
                uuid CHAR(36) NOT NULL,
                page_id VARCHAR(64) NOT NULL,
                page_name VARCHAR(250) NOT NULL,
                page_access_token LONGTEXT NOT NULL,
                connected_by_user_uuid CHAR(36) NOT NULL,
                connected_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE facebook_post_log (
                uuid CHAR(36) NOT NULL,
                event_uuid CHAR(36) NOT NULL,
                facebook_post_id VARCHAR(64) DEFAULT NULL,
                published_by_user_uuid CHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL,
                error_message LONGTEXT DEFAULT NULL,
                published_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid),
                INDEX facebook_post_log_event (event_uuid, published_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);

        $this->addSql("INSERT INTO permission (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), 'publish_facebook', 'Publikowanie postów wydarzeń na Facebooku', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'publish_facebook')");
        $this->addSql("UPDATE permission SET deleted_at = NULL, description = 'Publikowanie postów wydarzeń na Facebooku', active = 1 WHERE name = 'publish_facebook'");

        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at)
            SELECT rap.role_uuid, rap.access_uuid, p_new.uuid, NOW(), NULL
            FROM role_access_permission rap
            JOIN access a ON a.uuid = rap.access_uuid AND a.name = 'events' AND a.deleted_at IS NULL
            JOIN permission p_old ON p_old.uuid = rap.permission_uuid AND p_old.name = 'manage_event_media'
            JOIN permission p_new ON p_new.name = 'publish_facebook'
            WHERE rap.deleted_at IS NULL
            ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE facebook_post_log');
        $this->addSql('DROP TABLE facebook_page_connection');
    }
}
