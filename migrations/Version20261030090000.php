<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenant and user preferences with encrypted Brevo configuration and SMS quota ledger';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE application_preference (owner VARCHAR(36) NOT NULL, resource VARCHAR(32) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, PRIMARY KEY(owner, resource)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sms_delivery (id VARCHAR(36) NOT NULL, user_uuid VARCHAR(36) NOT NULL, period VARCHAR(7) NOT NULL, units INT NOT NULL, status VARCHAR(16) NOT NULL, provider_id VARCHAR(100) DEFAULT NULL, INDEX sms_period_status (period, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preferences and SMS accounting must not be silently deleted.');
    }
}
