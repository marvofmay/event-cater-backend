<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wydarzenia założone przed tą zmianą dostały powiadomienie przy utworzeniu.
 * Oznaczamy je jako wysłane, żeby przełącznik nie poszedł drugi raz.
 */
final class Version20261119220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember when the event-created notification was sent';
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
            $this->addSql('ALTER TABLE catering_event ADD creation_notice_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        $this->addSql('UPDATE catering_event SET creation_notice_sent_at = created_at WHERE creation_notice_sent_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$this->hasTable('catering_event') || !$this->hasColumn('catering_event', 'creation_notice_sent_at')) {
            return;
        }
        $this->addSql('ALTER TABLE catering_event DROP creation_notice_sent_at');
    }

    private function hasTable(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_key_exists(strtolower($column), array_change_key_case($columns));
    }
}
