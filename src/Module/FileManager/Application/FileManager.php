<?php

declare(strict_types=1);

namespace App\Module\FileManager\Application;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\FileManager\Application\Port\FileCatalog;
use App\Module\FileManager\Application\Port\PrivateFileStorage;
use App\Module\FileManager\Domain\FileName;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FileManager
{
    public const string ROOT = '00000000-0000-0000-0000-000000000000';
    public const int MAX_ENTRIES = 5000;
    public const int MAX_BYTES = 2 * 1024 * 1024 * 1024;

    public function __construct(private FileCatalog $catalog, private PrivateFileStorage $storage, private LoggerInterface $logger)
    {
    }

    public function snapshot(): array
    {
        return $this->catalog->transaction(null, function (): array {
            $rows = $this->catalog->rows();
            $root = $this->catalog->find(self::ROOT);
            return ['version' => (int) $root['version'], 'items' => array_values(array_map(static fn (array $row): array => [
                'uuid' => $row['uuid'], 'parentUuid' => $row['parent_uuid'] === self::ROOT ? null : $row['parent_uuid'],
                'name' => $row['name'], 'type' => $row['kind'], 'size' => (int) $row['size'], 'date' => str_replace(' ', 'T', $row['updated_at']).'Z',
            ], array_filter($rows, static fn (array $row): bool => $row['uuid'] !== self::ROOT))), 'usedBytes' => array_sum(array_column($rows, 'size')), 'maxBytes' => self::MAX_BYTES, 'maxEntries' => self::MAX_ENTRIES];
        });
    }

    public function create(string $parent, string $name, int $version, string $actor, ?UploadedFile $upload = null): string
    {
        $name = FileName::validate($name);
        $stored = null;
        try {
            return $this->catalog->transaction($version, function () use ($parent, $name, $actor, $upload, &$stored): string {
                $rows = $this->indexed();
                $this->folder($parent, $rows);
                $this->uniqueName($parent, $name, $rows);
                $size = $upload?->getSize();
                if ($upload && (!$upload->isValid() || !is_int($size))) throw new BusinessRuleViolation('Nie udało się przesłać pliku. Maksymalny rozmiar to 20 MiB.');
                $this->limits($rows, 1, $size ?? 0);
                if ($upload) $stored = $this->storage->store($upload, $name);
                $row = $this->newRow($parent, $name, $actor, $stored);
                $rows[$row['uuid']] = $row;
                $this->validateTree($rows);
                $this->catalog->insert($row);
                return $row['uuid'];
            });
        } catch (\Throwable $error) {
            if ($stored !== null) $this->discard($stored['blob_name']);
            throw $error;
        }
    }

    public function rename(string $uuid, string $name, int $version, string $actor): void
    {
        $name = FileName::validate($name);
        $this->catalog->transaction($version, function () use ($uuid, $name, $actor): void {
            $rows = $this->indexed();
            $row = $this->entry($uuid, $rows);
            if ($row['kind'] === 'file' && strtolower(pathinfo($row['name'], PATHINFO_EXTENSION)) !== strtolower(pathinfo($name, PATHINFO_EXTENSION))) throw new BusinessRuleViolation('Nie można zmienić rozszerzenia pliku.');
            $this->uniqueName($row['parent_uuid'], $name, $rows, $uuid);
            $changes = ['name' => $name, 'name_key' => FileName::key($name), 'updated_by' => $actor, 'updated_at' => gmdate('Y-m-d H:i:s')];
            $rows[$uuid] = array_replace($row, $changes);
            $this->validateTree($rows);
            $this->catalog->update($uuid, $changes);
        });
    }

    public function transfer(array $uuids, string $target, bool $copy, int $version, string $actor): void
    {
        $this->catalog->transaction($version, function () use ($uuids, $target, $copy, $actor): void {
            $rows = $this->indexed();
            $this->folder($target, $rows);
            $roots = $this->selection($uuids, $rows);
            $selected = $this->branches($roots, $rows);
            if (isset($selected[$target])) throw new BusinessRuleViolation('Nie można przenieść ani skopiować folderu do niego samego lub jego podfolderu.');
            if ($copy) $this->limits($rows, count($selected), array_sum(array_column($selected, 'size')));
            $mapping = [];
            foreach ($selected as $uuid => $row) $mapping[$uuid] = $copy ? Uuid::uuid4()->toString() : $uuid;
            $changes = [];
            foreach ($selected as $uuid => $row) {
                if (!$copy && !in_array($uuid, $roots, true)) continue;
                $parent = in_array($uuid, $roots, true) ? $target : $mapping[$row['parent_uuid']];
                $this->uniqueName($parent, $row['name'], $rows, $copy ? null : $uuid);
                $new = array_replace($row, ['uuid' => $mapping[$uuid], 'parent_uuid' => $parent, 'updated_by' => $actor, 'updated_at' => gmdate('Y-m-d H:i:s')]);
                if ($copy) $new = array_replace($new, ['created_by' => $actor, 'created_at' => $new['updated_at'], 'version' => 1]);
                $rows[$new['uuid']] = $new;
                $changes[] = $new;
            }
            $this->validateTree($rows);
            foreach ($changes as $row) {
                if ($copy) $this->catalog->insert($row);
                else $this->catalog->update($row['uuid'], ['parent_uuid' => $row['parent_uuid'], 'updated_by' => $actor, 'updated_at' => $row['updated_at']]);
            }
        });
    }

    public function delete(array $uuids, int $version): void
    {
        $blobs = $this->catalog->transaction($version, function () use ($uuids): array {
            $rows = $this->indexed();
            $selected = $this->branches($this->selection($uuids, $rows), $rows);
            $blobs = array_values(array_unique(array_filter(array_column($selected, 'blob_name'))));
            foreach (array_reverse($selected, true) as $row) $this->catalog->delete($row['uuid']);
            return $this->catalog->unreferencedBlobs($blobs);
        });
        foreach ($blobs as $blob) $this->discard($blob);
    }

    public function download(string $uuid): array
    {
        $row = $this->catalog->find($uuid);
        if (!$row || $row['kind'] !== 'file') throw new PlanningResourceNotFound('Nie znaleziono pliku.');
        return ['path' => $this->storage->path($row['blob_name']), 'name' => $row['name'], 'mime' => $row['mime_type']];
    }

    private function indexed(): array
    {
        return array_column($this->catalog->rows(), null, 'uuid');
    }

    private function entry(string $uuid, array $rows): array
    {
        if ($uuid === self::ROOT) throw new BusinessRuleViolation('Nie można zmieniać folderu głównego.');
        return $rows[$uuid] ?? throw new PlanningResourceNotFound('Nie znaleziono pliku lub folderu.');
    }

    private function folder(string $uuid, array $rows): void
    {
        $row = $rows[$uuid] ?? throw new PlanningResourceNotFound('Nie znaleziono folderu.');
        if ($row['kind'] !== 'folder') throw new BusinessRuleViolation('Wybierz folder docelowy.');
    }

    private function uniqueName(string $parent, string $name, array $rows, ?string $except = null): void
    {
        $key = FileName::key($name);
        foreach ($rows as $row) {
            if ($row['parent_uuid'] === $parent && $row['name_key'] === $key && $row['uuid'] !== $except) throw new PlanningConflict('W tym folderze istnieje już plik lub folder o tej nazwie.');
        }
    }

    private function limits(array $rows, int $count, int $size): void
    {
        if (count($rows) - 1 + $count > self::MAX_ENTRIES || array_sum(array_column($rows, 'size')) + $size > self::MAX_BYTES) throw new BusinessRuleViolation('Przekroczono limit Twojego dysku w tej firmie: 5000 plików i folderów lub 2 GiB.');
    }

    private function validateTree(array $rows): void
    {
        foreach ($rows as $row) {
            $depth = 0;
            $length = 0;
            while ($row['uuid'] !== self::ROOT) {
                $length += strlen($row['name']) + 1;
                if (++$depth > 32 || $length > 4096) throw new BusinessRuleViolation('Maksymalna głębokość folderów to 32, a długość ścieżki to 4096 bajtów.');
                $row = $rows[$row['parent_uuid']] ?? throw new \LogicException('Missing file parent.');
            }
        }
    }

    private function selection(array $uuids, array $rows): array
    {
        if ($uuids === [] || count($uuids) > 100) throw new BusinessRuleViolation('Wybierz od 1 do 100 plików lub folderów.');
        $uuids = array_values(array_unique($uuids));
        foreach ($uuids as $uuid) $this->entry($uuid, $rows);
        return array_values(array_filter($uuids, static function (string $uuid) use ($uuids, $rows): bool {
            $parent = $rows[$uuid]['parent_uuid'];
            while ($parent !== self::ROOT) {
                if (in_array($parent, $uuids, true)) return false;
                $parent = $rows[$parent]['parent_uuid'];
            }
            return true;
        }));
    }

    private function branches(array $roots, array $rows): array
    {
        $children = [];
        foreach ($rows as $uuid => $row) $children[$row['parent_uuid'] ?? ''][] = $uuid;
        $result = [];
        $visit = function (string $uuid) use (&$visit, &$result, $children, $rows): void {
            $result[$uuid] = $rows[$uuid];
            foreach ($children[$uuid] ?? [] as $child) $visit($child);
        };
        foreach ($roots as $uuid) $visit($uuid);
        return $result;
    }

    private function newRow(string $parent, string $name, string $actor, ?array $stored): array
    {
        return ['uuid' => Uuid::uuid4()->toString(), 'parent_uuid' => $parent, 'name' => $name, 'name_key' => FileName::key($name), 'kind' => $stored ? 'file' : 'folder', 'blob_name' => $stored['blob_name'] ?? null, 'mime_type' => $stored['mime_type'] ?? null, 'size' => $stored['size'] ?? 0, 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'), 'version' => 1];
    }

    private function discard(string $blob): void
    {
        try {
            $this->storage->discard($blob);
        } catch (\Throwable $error) {
            $this->logger->error('Private file cleanup failed.', ['blob' => $blob, 'exception' => $error]);
        }
    }
}
