<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Structured customer addresses; retain legacy address text without guessing its parts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_customer ADD city VARCHAR(200) DEFAULT NULL, ADD street VARCHAR(200) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_customer DROP city, DROP street, DROP postal_code, DROP country');
    }
}
