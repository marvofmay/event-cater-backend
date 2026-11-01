<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Single-use tenant-bound password recovery tokens stored as hashes'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_request (token_hash VARCHAR(64) NOT NULL, request_uuid VARCHAR(36) NOT NULL, user_uuid VARCHAR(36) NOT NULL, credential_version INT NOT NULL, email VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, PRIMARY KEY (token_hash), UNIQUE INDEX password_reset_request_uuid (request_uuid), INDEX password_reset_user (user_uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE password_reset_request'); }
}
