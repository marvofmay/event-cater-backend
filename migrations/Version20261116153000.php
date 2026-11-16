<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order event-store rows that share the same second by their microseconds';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('event_store') && !$schema->getTable('event_store')->hasColumn('created_at_us')) {
            $this->addSql('ALTER TABLE event_store ADD created_at_us INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('event_store') && $schema->getTable('event_store')->hasColumn('created_at_us')) {
            $this->addSql('ALTER TABLE event_store DROP created_at_us');
        }
    }
}
