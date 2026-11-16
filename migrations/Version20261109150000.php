<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trial plan fields and reminder markers for SaaS tenants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE saas_tenant ADD plan VARCHAR(16) NOT NULL DEFAULT 'paid'");
        $this->addSql('ALTER TABLE saas_tenant ADD trial_ends_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE saas_tenant ADD trial_reminded_5d_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE saas_tenant ADD trial_reminded_3d_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE saas_tenant ADD trial_reminded_24h_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX saas_tenant_trial_ends ON saas_tenant (plan, status, trial_ends_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX saas_tenant_trial_ends ON saas_tenant');
        $this->addSql('ALTER TABLE saas_tenant DROP plan, DROP trial_ends_at, DROP trial_reminded_5d_at, DROP trial_reminded_3d_at, DROP trial_reminded_24h_at');
    }
}
