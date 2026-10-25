<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add consumable category dictionary and preserve existing product categories.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE consumable_category (uuid VARCHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, version INT DEFAULT 1 NOT NULL, UNIQUE INDEX consumable_category_name (name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO consumable_category (uuid, name) SELECT UUID(), COALESCE(NULLIF(TRIM(category), ''), 'Inne') FROM consumable_item GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'Inne')");
        $this->addSql('ALTER TABLE consumable_item ADD category_uuid VARCHAR(36) DEFAULT NULL');
        $this->addSql("UPDATE consumable_item item JOIN consumable_category category ON category.name = COALESCE(NULLIF(TRIM(item.category), ''), 'Inne') SET item.category_uuid = category.uuid");
        $this->addSql('ALTER TABLE consumable_item MODIFY category_uuid VARCHAR(36) NOT NULL, DROP category, ADD INDEX consumable_category_lookup (category_uuid), ADD CONSTRAINT consumable_category_fk FOREIGN KEY (category_uuid) REFERENCES consumable_category (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumable_item ADD category VARCHAR(100) DEFAULT NULL');
        $this->addSql('UPDATE consumable_item item JOIN consumable_category category ON category.uuid = item.category_uuid SET item.category = category.name');
        $this->addSql('ALTER TABLE consumable_item MODIFY category VARCHAR(100) NOT NULL, DROP FOREIGN KEY consumable_category_fk, DROP INDEX consumable_category_lookup, DROP category_uuid');
        $this->addSql('DROP TABLE consumable_category');
    }
}
