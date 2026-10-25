<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Explicit central technical access and tenant-local administrator identities';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('saas_administrator')) {
            $this->addSql('ALTER TABLE saas_administrator ADD technical_access BOOLEAN NOT NULL DEFAULT 0');
        }
        $this->addSql('ALTER TABLE user ADD saas_administrator_uuid VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('saas_administrator')) {
            $this->addSql('ALTER TABLE saas_administrator DROP technical_access');
        }
        $this->addSql('ALTER TABLE user DROP saas_administrator_uuid');
    }
}
