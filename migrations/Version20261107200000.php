<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-event notification channels + deadline reminder markers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catering_event ADD notification_channels JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE event_preparation_list ADD deadline_reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE kitchen_order ADD due_reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catering_event DROP notification_channels');
        $this->addSql('ALTER TABLE event_preparation_list DROP deadline_reminder_sent_at');
        $this->addSql('ALTER TABLE kitchen_order DROP due_reminder_sent_at');
    }
}
