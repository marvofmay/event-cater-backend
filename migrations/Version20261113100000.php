<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marker that the paid-subscription reminder was sent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saas_tenant ADD paid_reminded_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saas_tenant DROP paid_reminded_at');
    }
}
