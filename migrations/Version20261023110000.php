<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Kitchen production cards, immutable released revisions and audit; no automatic release of existing events.';
    }
    public function isTransactional(): bool
    {
        return false;
    }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kitchen_history (uuid VARCHAR(36) NOT NULL, order_version INT NOT NULL, action VARCHAR(80) NOT NULL, details JSON NOT NULL, actor_uuid VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, order_uuid VARCHAR(36) NOT NULL, INDEX IDX_33BB94599C8E6AB1 (order_uuid), UNIQUE INDEX kitchen_audit_version (order_uuid, order_version), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE kitchen_order (uuid VARCHAR(36) NOT NULL, event_uuid VARCHAR(36) NOT NULL, number VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, version INT DEFAULT 1 NOT NULL, revision INT NOT NULL, source JSON NOT NULL, source_hash VARCHAR(64) NOT NULL, production_date VARCHAR(10) NOT NULL, plan JSON NOT NULL, progress JSON NOT NULL, pending_revision JSON DEFAULT NULL, released_by VARCHAR(36) DEFAULT NULL, released_at DATETIME DEFAULT NULL, handed_over_by VARCHAR(36) DEFAULT NULL, handed_over_at DATETIME DEFAULT NULL, cancellation_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4D748F98CEB41C0D (event_uuid), UNIQUE INDEX UNIQ_4D748F9896901F54 (number), INDEX kitchen_daily_plan (production_date, status), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE kitchen_revision (uuid VARCHAR(36) NOT NULL, number INT NOT NULL, snapshot JSON NOT NULL, actor_uuid VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, order_uuid VARCHAR(36) NOT NULL, INDEX IDX_9ECE65609C8E6AB1 (order_uuid), UNIQUE INDEX kitchen_revision_number (order_uuid, number), PRIMARY KEY (uuid)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE kitchen_history ADD CONSTRAINT FK_33BB94599C8E6AB1 FOREIGN KEY (order_uuid) REFERENCES kitchen_order (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kitchen_revision ADD CONSTRAINT FK_9ECE65609C8E6AB1 FOREIGN KEY (order_uuid) REFERENCES kitchen_order (uuid) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kitchen_history');
        $this->addSql('DROP TABLE kitchen_revision');
        $this->addSql('DROP TABLE kitchen_order');
    }
}
