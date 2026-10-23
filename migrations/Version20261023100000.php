<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catering preparation, consumable catalog and reusable templates; backfill existing event equipment without duplicating demand.';
    }
    public function isTransactional(): bool
    {
        return false;
    }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_preparation_list (uuid VARCHAR(36) NOT NULL, status VARCHAR(30) NOT NULL, plan_revision INT NOT NULL, responsible_uuid VARCHAR(36) DEFAULT NULL, responsible_name VARCHAR(250) DEFAULT NULL, deadline_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, approved_revision INT DEFAULT NULL, approved_by VARCHAR(36) DEFAULT NULL, approved_at DATETIME DEFAULT NULL, dispatched_by VARCHAR(36) DEFAULT NULL, dispatched_at DATETIME DEFAULT NULL, accepted_shortages_reason LONGTEXT DEFAULT NULL, accepted_shortages JSON NOT NULL, event_uuid VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_D0254D13CEB41C0D (event_uuid), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_preparation_line (uuid VARCHAR(36) NOT NULL, consumable_uuid VARCHAR(36) DEFAULT NULL, required_quantity NUMERIC(10, 3) DEFAULT NULL, name VARCHAR(200) NOT NULL, code VARCHAR(50) NOT NULL, unit VARCHAR(10) NOT NULL, prepared_quantity NUMERIC(10, 3) NOT NULL, loaded_quantity NUMERIC(10, 3) NOT NULL, responsible_uuid VARCHAR(36) DEFAULT NULL, responsible_name VARCHAR(250) DEFAULT NULL, source_location VARCHAR(200) DEFAULT NULL, instructions LONGTEXT DEFAULT NULL, prepared_by VARCHAR(36) DEFAULT NULL, prepared_at DATETIME DEFAULT NULL, loaded_by VARCHAR(36) DEFAULT NULL, loaded_at DATETIME DEFAULT NULL, rule JSON DEFAULT NULL, source_template_uuid VARCHAR(36) DEFAULT NULL, source_template_version INT DEFAULT NULL, preparation_uuid VARCHAR(36) NOT NULL, equipment_requirement_uuid VARCHAR(36) DEFAULT NULL, INDEX IDX_45F901FD63820665 (preparation_uuid), UNIQUE INDEX UNIQ_45F901FD707CDF33 (equipment_requirement_uuid), UNIQUE INDEX preparation_consumable_unique (preparation_uuid, consumable_uuid), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE preparation_template (uuid VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, template_items JSON NOT NULL, active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consumable_item (uuid VARCHAR(36) NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(200) NOT NULL, category VARCHAR(100) NOT NULL, unit VARCHAR(10) NOT NULL, description LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1B91CB7877153098 (code), INDEX consumable_search (active, name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_preparation_list ADD CONSTRAINT FK_D0254D13CEB41C0D FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_preparation_line ADD CONSTRAINT FK_45F901FD63820665 FOREIGN KEY (preparation_uuid) REFERENCES event_preparation_list (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_preparation_line ADD CONSTRAINT FK_45F901FD707CDF33 FOREIGN KEY (equipment_requirement_uuid) REFERENCES event_equipment_requirement (uuid) ON DELETE RESTRICT');
        $this->addSql('INSERT INTO event_preparation_list (uuid, event_uuid, status, plan_revision, accepted_shortages) SELECT UUID(), uuid, \'draft\', 1, JSON_ARRAY() FROM catering_event');
        $this->addSql('INSERT INTO event_preparation_line (uuid, preparation_uuid, equipment_requirement_uuid, name, code, unit, prepared_quantity, loaded_quantity) SELECT UUID(), p.uuid, r.uuid, r.name, r.code, r.unit, 0, 0 FROM event_equipment_requirement r INNER JOIN event_preparation_list p ON p.event_uuid = r.event_uuid');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_preparation_line');
        $this->addSql('DROP TABLE event_preparation_list');
        $this->addSql('DROP TABLE preparation_template');
        $this->addSql('DROP TABLE consumable_item');
    }
}
