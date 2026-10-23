<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catering events, team planning, menus, private media, audit history and equipment catalog.';
    }

    public function isTransactional(): bool
    {
        return false; // MySQL DDL commits implicitly.
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_attachment (uuid VARCHAR(36) NOT NULL, file_uuid VARCHAR(36) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, author_uuid VARCHAR(36) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, event_uuid VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_21B10094588338C8 (file_uuid), INDEX IDX_21B10094CEB41C0D (event_uuid), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_equipment_requirement (uuid VARCHAR(36) NOT NULL, equipment_uuid VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, code VARCHAR(50) NOT NULL, unit VARCHAR(10) NOT NULL, quantity INT NOT NULL, notes LONGTEXT DEFAULT NULL, event_uuid VARCHAR(36) NOT NULL, INDEX IDX_782ACCEFCEB41C0D (event_uuid), UNIQUE INDEX event_equipment_unique (event_uuid, equipment_uuid), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_staff_assignment (uuid VARCHAR(36) NOT NULL, employee_uuid VARCHAR(36) NOT NULL, employee_name VARCHAR(250) NOT NULL, functions JSON NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, event_uuid VARCHAR(36) NOT NULL, INDEX IDX_F087B7A3CEB41C0D (event_uuid), INDEX employee_schedule (employee_uuid, starts_at, ends_at), UNIQUE INDEX event_employee_unique (event_uuid, employee_uuid), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_history (uuid VARCHAR(36) NOT NULL, actor_uuid VARCHAR(36) NOT NULL, action VARCHAR(80) NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, revision INT NOT NULL, event_uuid VARCHAR(36) NOT NULL, INDEX IDX_A2EDD9E4CEB41C0D (event_uuid), UNIQUE INDEX event_history_revision (event_uuid, revision), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catering_event (uuid VARCHAR(36) NOT NULL, number VARCHAR(40) NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, event_date VARCHAR(10) NOT NULL, timezone VARCHAR(64) NOT NULL, ready_at DATETIME DEFAULT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, location JSON NOT NULL, guest_count INT DEFAULT NULL, coordinator_uuid VARCHAR(36) DEFAULT NULL, customer_name VARCHAR(200) DEFAULT NULL, contacts JSON NOT NULL, menu JSON NOT NULL, status VARCHAR(20) NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7DF5B53F96901F54 (number), INDEX event_calendar (event_date, status), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE equipment_item (uuid VARCHAR(36) NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(200) NOT NULL, category VARCHAR(100) NOT NULL, unit VARCHAR(10) NOT NULL, description LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_259FF41877153098 (code), INDEX equipment_search (active, name), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_attachment ADD CONSTRAINT FK_21B10094CEB41C0D FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_equipment_requirement ADD CONSTRAINT FK_782ACCEFCEB41C0D FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_staff_assignment ADD CONSTRAINT FK_F087B7A3CEB41C0D FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_history ADD CONSTRAINT FK_A2EDD9E4CEB41C0D FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_attachment');
        $this->addSql('DROP TABLE event_history');
        $this->addSql('DROP TABLE event_equipment_requirement');
        $this->addSql('DROP TABLE event_staff_assignment');
        $this->addSql('DROP TABLE catering_event');
        $this->addSql('DROP TABLE equipment_item');
    }
}
