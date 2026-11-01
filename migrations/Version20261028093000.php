<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow employees without a contract type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee MODIFY contract_type_uuid CHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT COUNT(*) FROM employee WHERE contract_type_uuid IS NULL'), 'Cannot require a contract type while employees without one exist.');
        $this->addSql('ALTER TABLE employee MODIFY contract_type_uuid CHAR(36) NOT NULL');
    }
}
