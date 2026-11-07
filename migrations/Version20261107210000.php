<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional comment when packer confirms preparation list ready';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_preparation_list ADD ready_comment LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_preparation_list ADD ready_by CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_preparation_list ADD ready_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_preparation_list DROP ready_comment');
        $this->addSql('ALTER TABLE event_preparation_list DROP ready_by');
        $this->addSql('ALTER TABLE event_preparation_list DROP ready_at');
    }
}
