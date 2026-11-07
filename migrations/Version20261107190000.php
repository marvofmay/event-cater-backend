<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-event notification recipient overrides (extra / excluded)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_notification_recipient_override (
            uuid VARCHAR(36) NOT NULL,
            event_uuid VARCHAR(36) NOT NULL,
            employee_uuid VARCHAR(36) NOT NULL,
            kind VARCHAR(20) NOT NULL,
            PRIMARY KEY(uuid),
            UNIQUE INDEX event_notification_recipient_unique (event_uuid, employee_uuid),
            INDEX IDX_event_notification_recipient_event (event_uuid),
            CONSTRAINT FK_event_notification_recipient_event FOREIGN KEY (event_uuid) REFERENCES catering_event (uuid) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_notification_recipient_override');
    }
}
