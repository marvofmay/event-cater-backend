<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202165248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access DROP FOREIGN KEY `FK_6692B546B4315AA`');
        $this->addSql('ALTER TABLE access CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE module_uuid module_uuid CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE access ADD CONSTRAINT FK_6692B546B4315AA FOREIGN KEY (module_uuid) REFERENCES module (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE address CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE company CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE contact CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE contract_type CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE department CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('DROP INDEX index_active ON employee');
        $this->addSql('ALTER TABLE employee ADD company_uuid CHAR(36) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE department_uuid department_uuid CHAR(36) NOT NULL, CHANGE position_uuid position_uuid CHAR(36) NOT NULL, CHANGE contract_type_uuid contract_type_uuid CHAR(36) NOT NULL, CHANGE role_uuid role_uuid CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A192124A48 FOREIGN KEY (company_uuid) REFERENCES company (uuid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_5D9F75A192124A48 ON employee (company_uuid)');
        $this->addSql('CREATE INDEX internal_code ON employee (internal_code)');
        $this->addSql('ALTER TABLE industry CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE module CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE note CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE notification_template_setting CHANGE event_name event_name VARCHAR(250) NOT NULL');
        $this->addSql('ALTER TABLE notification_template_setting ADD CONSTRAINT FK_46C8835C41E832AD FOREIGN KEY (event_name) REFERENCES notification_event_setting (event_name) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_template_setting ADD CONSTRAINT FK_46C8835CEB4193E6 FOREIGN KEY (channel_code) REFERENCES notification_channel_setting (channel_code) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE role CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE user CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649e7927c74 TO unique_email');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access DROP FOREIGN KEY FK_6692B546B4315AA');
        $this->addSql('ALTER TABLE access CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE module_uuid module_uuid CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE access ADD CONSTRAINT `FK_6692B546B4315AA` FOREIGN KEY (module_uuid) REFERENCES module (uuid) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE address CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE company CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE contact CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE contract_type CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE department CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A192124A48');
        $this->addSql('DROP INDEX IDX_5D9F75A192124A48 ON employee');
        $this->addSql('DROP INDEX internal_code ON employee');
        $this->addSql('ALTER TABLE employee DROP company_uuid, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE department_uuid department_uuid CHAR(36) DEFAULT NULL, CHANGE position_uuid position_uuid CHAR(36) DEFAULT NULL, CHANGE contract_type_uuid contract_type_uuid CHAR(36) DEFAULT NULL, CHANGE role_uuid role_uuid CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX index_active ON employee (active)');
        $this->addSql('ALTER TABLE industry CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE note CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE notification_template_setting DROP FOREIGN KEY FK_46C8835C41E832AD');
        $this->addSql('ALTER TABLE notification_template_setting DROP FOREIGN KEY FK_46C8835CEB4193E6');
        $this->addSql('ALTER TABLE notification_template_setting CHANGE event_name event_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE permission CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE role CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX unique_email TO UNIQ_8D93D649E7927C74');
    }
}
