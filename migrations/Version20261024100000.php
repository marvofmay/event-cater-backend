<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private photo galleries to equipment catalog items.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_photo (uuid VARCHAR(36) NOT NULL, equipment_uuid VARCHAR(36) NOT NULL, name VARCHAR(150) NOT NULL, stored_name VARCHAR(50) NOT NULL, mime_type VARCHAR(50) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, INDEX equipment_photo_item (equipment_uuid), PRIMARY KEY (uuid), CONSTRAINT equipment_photo_fk FOREIGN KEY (equipment_uuid) REFERENCES equipment_item (uuid) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipment_photo');
    }
}
