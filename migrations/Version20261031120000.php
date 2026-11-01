<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenant customer directory and event customer reference; preserve existing customer names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_customer (uuid VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, nip VARCHAR(10) DEFAULT NULL, regon VARCHAR(14) DEFAULT NULL, krs VARCHAR(10) DEFAULT NULL, address VARCHAR(500) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL, email VARCHAR(254) DEFAULT NULL, INDEX event_customer_name (name), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catering_event ADD customer_uuid VARCHAR(36) DEFAULT NULL');
        $this->addSql("INSERT INTO event_customer (uuid, name) SELECT UUID(), existing.name FROM (SELECT MIN(TRIM(customer_name)) AS name FROM catering_event WHERE customer_name IS NOT NULL AND TRIM(customer_name) <> '' GROUP BY TRIM(customer_name)) existing");
        $this->addSql('UPDATE catering_event event INNER JOIN event_customer customer ON customer.name = TRIM(event.customer_name) COLLATE utf8mb4_unicode_ci SET event.customer_uuid = customer.uuid');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catering_event DROP customer_uuid');
        $this->addSql('DROP TABLE event_customer');
    }
}
