<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Employee phone logins and mandatory initial password change'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY email VARCHAR(100) DEFAULT NULL, ADD login_phone VARCHAR(15) DEFAULT NULL, ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0, ADD credential_version INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE UNIQUE INDEX unique_login_phone ON user (login_phone)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Phone-only accounts cannot be converted back into email accounts.');
    }
}
