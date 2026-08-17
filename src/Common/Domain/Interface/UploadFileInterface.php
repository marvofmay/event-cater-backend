<?php

declare(strict_types=1);

namespace App\Common\Domain\Interface;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploadFileInterface
{
    public function uploadFile(UploadedFile $file): void;

    public function getUploadedFile(): ?UploadedFile;

    public function isAllowedExtension(string $extension): bool;

    public function isExpectedExtension(string $extension): bool;

    public function getFileName(): ?string;
}
