<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Module\FileManager\Domain\FileName;
use App\Module\FileManager\Infrastructure\CurrentFileOwner;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ramsey\Uuid\Uuid;

final class Version20261101140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Isolate file manager catalogs and revisions per user within each tenant';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative("SELECT uuid, parent_uuid, name, kind, created_by FROM managed_file WHERE uuid <> '00000000-0000-0000-0000-000000000000'");
        $indexed = array_column($rows, null, 'uuid');
        $this->addSql('ALTER TABLE managed_file ADD owner_uuid CHAR(36) DEFAULT NULL, ADD INDEX managed_file_owner (owner_uuid, created_at, uuid)');
        $this->addSql("UPDATE managed_file SET owner_uuid = created_by WHERE uuid <> '00000000-0000-0000-0000-000000000000'");
        $owners = array_values(array_unique(array_filter(array_column($rows, 'created_by'))));
        foreach ($owners as $owner) {
            $this->abortIf(!Uuid::isValid($owner), 'Invalid file owner. Manual migration review required.');
            $root = CurrentFileOwner::rootFor($owner);
            $this->addSql("INSERT INTO managed_file (uuid, owner_uuid, name, name_key, kind, created_at, updated_at) VALUES (?, ?, '', '', 'folder', UTC_TIMESTAMP(), UTC_TIMESTAMP())", [$root, $owner]);
            $names = [];
            foreach ($rows as $row) {
                if ($row['created_by'] !== $owner || ($indexed[$row['parent_uuid']]['created_by'] ?? null) === $owner) continue;
                $name = $row['name'];
                $key = FileName::key($name);
                if (isset($names[$key])) {
                    $extension = $row['kind'] === 'file' ? pathinfo($name, PATHINFO_EXTENSION) : '';
                    $base = $extension === '' ? $name : pathinfo($name, PATHINFO_FILENAME);
                    $name = mb_substr($base, 0, 70).' ['.$row['uuid'].']'.($extension === '' ? '' : '.'.$extension);
                    $key = FileName::key($name);
                }
                $names[$key] = true;
                $this->addSql('UPDATE managed_file SET parent_uuid = ?, name = ?, name_key = ? WHERE uuid = ?', [$root, $name, $key, $row['uuid']]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Private user catalogs cannot safely be merged back into a shared tenant disk. Restore a reviewed backup instead.');
    }
}
