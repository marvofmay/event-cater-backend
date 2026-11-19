<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schedule, deactivate and archive company notices';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $manager = $this->connection->createSchemaManager();
        if (!$manager->tablesExist(['company_notice'])) {
            return;
        }
        $columns = $manager->listTableColumns('company_notice');
        if (isset($columns['publish_at'])) {
            return;
        }
        $this->connection->executeStatement(<<<'SQL'
            ALTER TABLE company_notice
                ADD publish_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '(DC2Type:datetime_immutable)',
                ADD active TINYINT(1) NOT NULL DEFAULT 1,
                ADD archived_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                ADD INDEX company_notice_publication (active, publish_at, archived_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $manager = $this->connection->createSchemaManager();
        if (!$manager->tablesExist(['company_notice'])) {
            return;
        }
        $columns = $manager->listTableColumns('company_notice');
        if (!isset($columns['publish_at'])) {
            return;
        }
        $this->connection->executeStatement('ALTER TABLE company_notice DROP INDEX company_notice_publication, DROP publish_at, DROP active, DROP archived_at');
    }
}
