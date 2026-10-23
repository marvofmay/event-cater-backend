<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add equipment categories, employee slang names and owned stock quantities; preserve existing categories.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_category (uuid VARCHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, version INT DEFAULT 1 NOT NULL, UNIQUE INDEX equipment_category_name (name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO equipment_category (uuid, name) SELECT UUID(), TRIM(category) FROM equipment_item GROUP BY TRIM(category)');
        $this->addSql('ALTER TABLE equipment_item ADD category_uuid VARCHAR(36) DEFAULT NULL, ADD slang_name VARCHAR(200) DEFAULT NULL, ADD stock_quantity INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE equipment_item item JOIN equipment_category category ON category.name = TRIM(item.category) SET item.category_uuid = category.uuid');
        $this->addSql('ALTER TABLE equipment_item MODIFY category_uuid VARCHAR(36) NOT NULL, DROP category, ADD INDEX equipment_category_lookup (category_uuid), ADD CONSTRAINT equipment_category_fk FOREIGN KEY (category_uuid) REFERENCES equipment_category (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item ADD category VARCHAR(100) DEFAULT NULL');
        $this->addSql('UPDATE equipment_item item JOIN equipment_category category ON category.uuid = item.category_uuid SET item.category = category.name');
        $this->addSql('ALTER TABLE equipment_item MODIFY category VARCHAR(100) NOT NULL, DROP FOREIGN KEY equipment_category_fk, DROP INDEX equipment_category_lookup, DROP category_uuid, DROP slang_name, DROP stock_quantity');
        $this->addSql('DROP TABLE equipment_category');
    }
}
