<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261023130000 extends AbstractMigration
{
    public function getDescription(): string { return 'SaaS control plane, tenant lifecycle, administrator sessions and audit trail'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE saas_tenant (uuid CHAR(36) PRIMARY KEY, slug VARCHAR(63) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL, contact_email VARCHAR(180) NOT NULL, database_name VARCHAR(64) NOT NULL UNIQUE, status VARCHAR(16) NOT NULL, provisioning VARCHAR(16) NOT NULL, version INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->addSql("INSERT INTO saas_tenant VALUES ('00000000-0000-4000-8000-000000000001','default','Firma domyślna','',DATABASE(),'active','ready',1,NOW(),NOW())");
        $this->addSql('CREATE TABLE saas_administrator (uuid CHAR(36) PRIMARY KEY, email VARCHAR(180) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->addSql('CREATE TABLE saas_session (token_hash CHAR(64) PRIMARY KEY, administrator_uuid CHAR(36) NOT NULL, expires_at DATETIME NOT NULL, FOREIGN KEY(administrator_uuid) REFERENCES saas_administrator(uuid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->addSql('CREATE TABLE saas_audit (uuid CHAR(36) PRIMARY KEY, tenant_uuid CHAR(36) NOT NULL, actor_uuid CHAR(36) NOT NULL, action VARCHAR(60) NOT NULL, reason VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL, INDEX tenant_history(tenant_uuid, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->addSql('ALTER TABLE user ADD tenant_administrator BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql("UPDATE user SET tenant_administrator=1 WHERE email='admin.ec@gmail.com'");
    }
    public function down(Schema $schema): void
    {
        foreach (['saas_audit','saas_session','saas_administrator','saas_tenant'] as $table) { $this->addSql('DROP TABLE '.$table); }
        $this->addSql('ALTER TABLE user DROP tenant_administrator');
    }
}
