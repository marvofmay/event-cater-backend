<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow equipment to be stored without a catalog code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item CHANGE code code VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_equipment_requirement CHANGE code code VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_preparation_line CHANGE code code VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE equipment_item SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL");
        $this->addSql("UPDATE event_equipment_requirement SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL");
        $this->addSql("UPDATE event_preparation_line SET code = CONCAT('EQ-', UPPER(LEFT(REPLACE(uuid, '-', ''), 12))) WHERE code IS NULL");
        $this->addSql('ALTER TABLE equipment_item CHANGE code code VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE event_equipment_requirement CHANGE code code VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE event_preparation_line CHANGE code code VARCHAR(50) NOT NULL');
    }
}
