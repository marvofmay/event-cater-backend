<?php

declare(strict_types=1);

namespace App\Module\Support\Application\Port;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface AttachmentStorage
{
    /**
     * Stores accepted images and removes them again when a later file in the same batch is rejected.
     *
     * @param list<UploadedFile> $files
     * @return list<array{storage_key: string, original_name: string, mime_type: string, size_bytes: int}>
     */
    public function accept(array $files): array;

    /** @param list<string> $keys */
    public function discard(array $keys): void;

    public function path(string $key): string;
}
