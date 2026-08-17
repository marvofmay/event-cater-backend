<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Interface\File;

use App\Module\System\Domain\Entity\File;

interface FileWriterInterface
{
    public function saveFile(File $file): void;
}
