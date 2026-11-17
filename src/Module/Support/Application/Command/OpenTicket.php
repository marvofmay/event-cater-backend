<?php

declare(strict_types=1);

namespace App\Module\Support\Application\Command;

use App\Module\Support\Application\Reporter;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class OpenTicket
{
    /** @param list<UploadedFile> $files */
    public function __construct(
        public Reporter $reporter,
        public string $category,
        public string $subject,
        public string $message,
        public array $files,
    ) {
    }
}
