<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Show staff functions in the template and drop the packing staff note';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['notification_template_setting'])) {
            return;
        }
        $this->connection->executeStatement(<<<'SQL'
            UPDATE notification_template_setting
            SET content = REPLACE(content, '{{functions}}', 'Twoje funkcje: {{functions}}')
            WHERE event_name = 'EventStaffAssigned'
              AND deleted_at IS NULL
              AND content LIKE '%{{functions}}%'
              AND content NOT LIKE '%Twoje funkcje:%'
        SQL);
        $this->connection->executeStatement(<<<'SQL'
            UPDATE notification_template_setting
            SET content = REPLACE(content, '{{staffNote}}', '')
            WHERE event_name = 'EventPackerAssigned'
              AND deleted_at IS NULL
              AND content LIKE '%{{staffNote}}%'
        SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['notification_template_setting'])) {
            return;
        }
        $this->connection->executeStatement(<<<'SQL'
            UPDATE notification_template_setting
            SET content = REPLACE(content, 'Twoje funkcje: {{functions}}', '{{functions}}')
            WHERE event_name = 'EventStaffAssigned'
              AND deleted_at IS NULL
              AND content LIKE '%Twoje funkcje: {{functions}}%'
        SQL);
        $this->connection->executeStatement(<<<'SQL'
            UPDATE notification_template_setting
            SET content = CONCAT(content, '{{staffNote}}')
            WHERE event_name = 'EventPackerAssigned'
              AND deleted_at IS NULL
              AND content = 'Odpowiadasz za skompletowanie kateringu na wydarzenie „{{eventTitle}}” ({{eventDate}}).'
        SQL);
    }
}
