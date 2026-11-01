<?php

declare(strict_types=1);

namespace App\Module\FileManager\Application\Port;

interface FileCatalog
{
    public function transaction(?int $version, callable $operation): mixed;
    public function rows(): array;
    public function find(string $uuid): ?array;
    public function insert(array $row): void;
    public function update(string $uuid, array $changes): void;
    public function delete(string $uuid): void;
    public function unreferencedBlobs(array $blobs): array;
}
