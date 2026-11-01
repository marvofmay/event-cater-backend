<?php

declare(strict_types=1);

namespace App\Module\FileManager\Infrastructure;

use App\Common\Domain\Exception\PlanningConflict;
use App\Module\FileManager\Application\FileManager;
use App\Module\FileManager\Application\Port\FileCatalog;
use Doctrine\DBAL\Connection;

final readonly class DbalFileCatalog implements FileCatalog
{
    public function __construct(private Connection $connection, private CurrentFileOwner $owner)
    {
    }

    public function transaction(?int $version, callable $operation): mixed
    {
        $owner = $this->owner->uuid();
        $root = CurrentFileOwner::rootFor($owner);
        $this->connection->executeStatement("INSERT IGNORE INTO managed_file (uuid, owner_uuid, name, name_key, kind, created_at, updated_at) VALUES (?, ?, '', '', 'folder', UTC_TIMESTAMP(), UTC_TIMESTAMP())", [$root, $owner]);
        return $this->connection->transactional(function () use ($version, $operation, $owner, $root): mixed {
            $current = $this->connection->fetchOne('SELECT version FROM managed_file WHERE uuid = ? AND owner_uuid = ? FOR UPDATE', [$root, $owner]);
            if ($current === false) throw new \LogicException('Missing file manager root. Run migrations.');
            if ($version !== null && $version !== (int) $current) throw new PlanningConflict('Pliki zmieniły się. Odśwież widok i ponów operację.');
            $result = $operation();
            if ($version !== null) $this->connection->executeStatement('UPDATE managed_file SET version = version + 1, updated_at = UTC_TIMESTAMP() WHERE uuid = ? AND owner_uuid = ?', [$root, $owner]);
            return $result;
        });
    }

    public function rows(): array
    {
        return array_map($this->exposeRoot(...), $this->connection->fetchAllAssociative('SELECT * FROM managed_file WHERE owner_uuid = ? ORDER BY created_at, uuid LIMIT 5001', [$this->owner->uuid()]));
    }

    public function find(string $uuid): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM managed_file WHERE uuid = ? AND owner_uuid = ?', [$this->storedUuid($uuid), $this->owner->uuid()]);
        return $row ? $this->exposeRoot($row) : null;
    }

    public function insert(array $row): void
    {
        $row['owner_uuid'] = $this->owner->uuid();
        $row['parent_uuid'] = $this->storedUuid($row['parent_uuid']);
        $this->connection->insert('managed_file', $row);
    }

    public function update(string $uuid, array $changes): void
    {
        unset($changes['owner_uuid'], $changes['uuid']);
        if (isset($changes['parent_uuid'])) $changes['parent_uuid'] = $this->storedUuid($changes['parent_uuid']);
        $this->connection->update('managed_file', $changes, ['uuid' => $uuid, 'owner_uuid' => $this->owner->uuid()]);
    }

    public function delete(string $uuid): void
    {
        $this->connection->delete('managed_file', ['uuid' => $uuid, 'owner_uuid' => $this->owner->uuid()]);
    }

    public function unreferencedBlobs(array $blobs): array
    {
        if ($blobs === []) return [];
        $referenced = $this->connection->fetchFirstColumn('SELECT DISTINCT blob_name FROM managed_file WHERE blob_name IN (?)', [$blobs], [\Doctrine\DBAL\ArrayParameterType::STRING]);
        return array_values(array_diff($blobs, $referenced));
    }

    private function storedUuid(string $uuid): string
    {
        return $uuid === FileManager::ROOT ? CurrentFileOwner::rootFor($this->owner->uuid()) : $uuid;
    }

    private function exposeRoot(array $row): array
    {
        $root = CurrentFileOwner::rootFor($this->owner->uuid());
        if ($row['uuid'] === $root) $row['uuid'] = FileManager::ROOT;
        if ($row['parent_uuid'] === $root) $row['parent_uuid'] = FileManager::ROOT;
        return $row;
    }
}
