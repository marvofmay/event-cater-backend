<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add equipment storage location library and optional catalog assignment.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_location (uuid VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, version INT DEFAULT 1 NOT NULL, UNIQUE INDEX equipment_location_name (name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE equipment_item ADD storage_location_uuid VARCHAR(36) DEFAULT NULL, ADD INDEX equipment_storage_location (storage_location_uuid), ADD CONSTRAINT equipment_storage_location_fk FOREIGN KEY (storage_location_uuid) REFERENCES equipment_location (uuid) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY equipment_storage_location_fk, DROP INDEX equipment_storage_location, DROP storage_location_uuid');
        $this->addSql('DROP TABLE equipment_location');
    }
}
