<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\File;

use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Interface\File\FileWriterInterface;

readonly class FileCreator
{
    public function __construct(private FileWriterInterface $fileWriterRepository)
    {
    }

    public function create(File $file): void
    {
        $this->fileWriterRepository->saveFile($file);
    }
}
