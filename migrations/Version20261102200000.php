<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store Expo push tokens per user for mobile message notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_push_token (
                uuid CHAR(36) NOT NULL,
                user_uuid CHAR(36) NOT NULL,
                token VARCHAR(255) NOT NULL,
                platform VARCHAR(20) NOT NULL,
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid),
                UNIQUE INDEX user_push_token_token_unique (token),
                INDEX user_push_token_user (user_uuid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_push_token');
    }
}
