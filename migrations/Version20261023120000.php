<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reusable catering venues; events retain independent address and contact snapshots.';
    }
    public function isTransactional(): bool
    {
        return false;
    }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_venue (uuid VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, location JSON NOT NULL, contacts JSON NOT NULL, notes LONGTEXT DEFAULT NULL, service_mode VARCHAR(10) NOT NULL, active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, INDEX event_venue_search (active, name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_venue');
    }
}
