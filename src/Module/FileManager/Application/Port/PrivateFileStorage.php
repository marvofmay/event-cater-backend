<?php

declare(strict_types=1);

namespace App\Module\FileManager\Application\Port;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface PrivateFileStorage
{
    public function store(UploadedFile $file, string $name): array;
    public function path(string $blob): string;
    public function discard(string $blob): void;
}
