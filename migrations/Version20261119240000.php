<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The team-tab send loads the event, its recipient overrides and the kitchen
 * order through Doctrine. A copied company database can be missing those
 * pieces even when the page itself still opens.
 */
final class Version20261119240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure the event creation notice can be stored and sent';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasTable('catering_event')) {
            return;
        }
        if (!$this->hasColumn('catering_event', 'creation_notice_sent_at')) {
            $this->connection->executeStatement('ALTER TABLE catering_event ADD creation_notice_sent_at DATETIME DEFAULT NULL');
        }
        if (!$this->hasColumn('catering_event', 'notification_channels')) {
            $this->connection->executeStatement('ALTER TABLE catering_event ADD notification_channels JSON DEFAULT NULL');
        }
        if (!$this->hasTable('event_notification_recipient_override')) {
            $collation = (string) $this->connection->fetchOne(
                "SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'catering_event' AND COLUMN_NAME = 'uuid'",
            );
            $collate = preg_match('/^[A-Za-z0-9_]+$/', $collation) === 1 ? ' COLLATE '.$collation : '';
            $this->connection->executeStatement(<<<SQL
                CREATE TABLE event_notification_recipient_override (
                    uuid VARCHAR(36) NOT NULL,
                    event_uuid VARCHAR(36) NOT NULL,
                    employee_uuid VARCHAR(36) NOT NULL,
                    kind VARCHAR(20) NOT NULL,
                    PRIMARY KEY (uuid),
                    UNIQUE INDEX event_notification_recipient_unique (event_uuid, employee_uuid),
                    INDEX IDX_event_notification_recipient_event (event_uuid),
                    CONSTRAINT FK_event_notification_recipient_event FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4{$collate} ENGINE = InnoDB
            SQL);
        }
        if ($this->hasTable('kitchen_order') && !$this->hasColumn('kitchen_order', 'due_reminder_sent_at')) {
            $this->connection->executeStatement('ALTER TABLE kitchen_order ADD due_reminder_sent_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
    }

    private function hasTable(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        ) > 0;
    }
}
