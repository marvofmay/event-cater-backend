<?php

declare(strict_types=1);

namespace App\tests\unit\module\fileManager;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\FileManager\Application\FileManager;
use App\Module\FileManager\Application\Port\FileCatalog;
use App\Module\FileManager\Application\Port\PrivateFileStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileManagerTest extends TestCase
{
    public function testQuotaIsCheckedBeforeStoringBytes(): void
    {
        $catalog = $this->createMock(FileCatalog::class);
        $catalog->method('transaction')->willReturnCallback(static fn ($version, callable $operation) => $operation());
        $catalog->method('rows')->willReturn([
            ['uuid' => FileManager::ROOT, 'parent_uuid' => null, 'kind' => 'folder', 'size' => 0],
            ['uuid' => '10000000-0000-4000-8000-000000000001', 'parent_uuid' => FileManager::ROOT, 'kind' => 'file', 'size' => FileManager::MAX_BYTES, 'name_key' => 'existing'],
        ]);
        $storage = $this->createMock(PrivateFileStorage::class);
        $storage->expects(self::never())->method('store');
        $catalog->expects(self::never())->method('insert');
        $path = tempnam(sys_get_temp_dir(), 'quota-test-');
        file_put_contents($path, 'test');
        try {
            $this->expectException(BusinessRuleViolation::class);
            (new FileManager($catalog, $storage, new NullLogger()))->create(FileManager::ROOT, 'test.txt', 1, 'actor', new UploadedFile($path, 'test.txt', test: true));
        } finally {
            unlink($path);
        }
    }

    public function testFailedCatalogWriteCleansUpNewlyStoredBlob(): void
    {
        $catalog = $this->createMock(FileCatalog::class);
        $catalog->method('transaction')->willReturnCallback(static fn ($version, callable $operation) => $operation());
        $catalog->method('rows')->willReturn([['uuid' => FileManager::ROOT, 'parent_uuid' => null, 'kind' => 'folder', 'size' => 0]]);
        $catalog->method('insert')->willThrowException(new \RuntimeException('Database unavailable'));
        $storage = $this->createMock(PrivateFileStorage::class);
        $storage->method('store')->willReturn(['blob_name' => 'new-blob', 'size' => 4, 'mime_type' => 'text/plain']);
        $storage->expects(self::once())->method('discard')->with('new-blob');
        $path = tempnam(sys_get_temp_dir(), 'rollback-test-');
        file_put_contents($path, 'test');
        try {
            $this->expectException(\RuntimeException::class);
            (new FileManager($catalog, $storage, new NullLogger()))->create(FileManager::ROOT, 'test.txt', 1, 'actor', new UploadedFile($path, 'test.txt', test: true));
        } finally {
            unlink($path);
        }
    }
}
