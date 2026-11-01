<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow employees without an employment start date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee MODIFY employment_from DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT COUNT(*) FROM employee WHERE employment_from IS NULL'), 'Cannot require an employment start date while employees without one exist.');
        $this->addSql('ALTER TABLE employee MODIFY employment_from DATE NOT NULL');
    }
}
