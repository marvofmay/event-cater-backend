<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Active SaaS administrators enter every tenant as technical employees';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('saas_administrator')) {
            $this->addSql('UPDATE saas_administrator SET technical_access = 1 WHERE active = 1');
        }
    }

    public function down(Schema $schema): void
    {
        // The grant stays. Deactivate the SaaS account to remove tenant access.
    }
}
