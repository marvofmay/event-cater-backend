<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add work hours modules, accesses, employee role grants and work_hours_entry table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE work_hours_entry (
                uuid CHAR(36) NOT NULL,
                employee_uuid CHAR(36) NOT NULL,
                employee_name VARCHAR(250) NOT NULL,
                work_date DATE NOT NULL,
                minutes INT NOT NULL,
                comment LONGTEXT NOT NULL,
                kind VARCHAR(32) NOT NULL,
                event_uuid CHAR(36) DEFAULT NULL,
                created_by_user_uuid CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (uuid),
                INDEX work_hours_employee_date (employee_uuid, work_date),
                INDEX work_hours_date (work_date),
                INDEX work_hours_event (event_uuid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
        SQL);

        $this->addSql("INSERT INTO module (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), 'my_hours', 'Podgląd i raportowanie własnych godzin pracy', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'my_hours')");
        $this->addSql("INSERT INTO module (uuid, name, description, active, created_at, deleted_at) SELECT UUID(), 'employee_hours', 'Podgląd godzin pracy pracowników firmy', 1, NOW(), NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'employee_hours')");
        $this->addSql("UPDATE module SET deleted_at = NULL WHERE name IN ('my_hours', 'employee_hours')");
        $this->addSql("INSERT INTO access (uuid, module_uuid, name, description, active, created_at, deleted_at) SELECT UUID(), m.uuid, 'my_hours', 'Moje godziny pracy — podgląd i raportowanie własnego czasu', 1, NOW(), NULL FROM module m WHERE m.name = 'my_hours' AND NOT EXISTS (SELECT 1 FROM access WHERE name = 'my_hours')");
        $this->addSql("INSERT INTO access (uuid, module_uuid, name, description, active, created_at, deleted_at) SELECT UUID(), m.uuid, 'employee_hours', 'Godziny pracowników — podgląd czasu pracy zespołu', 1, NOW(), NULL FROM module m WHERE m.name = 'employee_hours' AND NOT EXISTS (SELECT 1 FROM access WHERE name = 'employee_hours')");
        $this->addSql("UPDATE access SET deleted_at = NULL WHERE name IN ('my_hours', 'employee_hours')");

        $this->addSql("INSERT INTO role_access (role_uuid, access_uuid, created_at, deleted_at) SELECT r.uuid, a.uuid, NOW(), NULL FROM role r CROSS JOIN access a WHERE r.name = 'Pracownik' AND r.deleted_at IS NULL AND a.name = 'my_hours' ON DUPLICATE KEY UPDATE deleted_at = NULL");
        $this->addSql("INSERT INTO role_access_permission (role_uuid, access_uuid, permission_uuid, created_at, deleted_at) SELECT ra.role_uuid, ra.access_uuid, p.uuid, NOW(), NULL FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid AND a.name = 'my_hours' JOIN permission p ON p.name IN ('list', 'view', 'create', 'update', 'delete') WHERE ra.deleted_at IS NULL ON DUPLICATE KEY UPDATE deleted_at = NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE work_hours_entry');
    }
}
