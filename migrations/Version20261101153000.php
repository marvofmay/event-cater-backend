<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align work_hours_entry collation with tenant tables (utf8mb4_0900_ai_ci)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_hours_entry CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_hours_entry CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}
