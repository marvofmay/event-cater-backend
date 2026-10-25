<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark employee PESEL identifiers as fictitious when explicitly declared.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee ADD is_pesel_fake TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee DROP is_pesel_fake');
    }
}
