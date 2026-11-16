<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monthly subscription payments confirmed by Tpay';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saas_tenant ADD paid_until DATETIME DEFAULT NULL');
        $this->addSql("CREATE TABLE saas_payment (
            uuid CHAR(36) NOT NULL,
            tenant_uuid CHAR(36) NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            currency CHAR(3) NOT NULL,
            status VARCHAR(16) NOT NULL,
            tpay_transaction_id VARCHAR(64) DEFAULT NULL,
            tpay_title VARCHAR(64) DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (uuid),
            UNIQUE INDEX saas_payment_tpay (tpay_transaction_id),
            INDEX saas_payment_tenant (tenant_uuid, created_at)
        ) DEFAULT CHARACTER SET utf8mb4");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE saas_payment');
        $this->addSql('ALTER TABLE saas_tenant DROP paid_until');
    }
}
