<?php

declare(strict_types=1);

namespace App\Module\CompanyDocument\Infrastructure\Storage;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CompanyDocumentStorage
{
    private const array EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/storage/company-documents')] private string $directory,
        private ?\App\Module\Saas\Infrastructure\TenantContext $tenantContext = null,
    ) {
    }

    private function storageDirectory(): string
    {
        $tenant = $this->tenantContext?->current();

        return !$tenant || $tenant['slug'] === 'default' ? $this->directory : $this->directory.'/'.$tenant['uuid'];
    }

    /** @return array{storedName: string, fileName: string, mimeType: string, size: int} */
    public function store(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new BusinessRuleViolation('Nie udało się wgrać pliku albo przekroczono limit serwera.');
        }
        $size = $file->getSize();
        if (!is_int($size) || $size < 1 || $size > 25 * 1024 * 1024) {
            throw new BusinessRuleViolation('Plik musi mieć od 1 B do 25 MiB.');
        }
        $mime = $file->getMimeType() ?: '';
        if (!isset(self::EXTENSIONS[$mime])) {
            throw new BusinessRuleViolation('Dozwolone: PDF, DOC/DOCX, XLS/XLSX, TXT, JPEG, PNG, WebP.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getPathname()) === false) {
            throw new BusinessRuleViolation('Nieprawidłowy plik obrazu.');
        }
        $extension = self::EXTENSIONS[$mime];
        $name = Uuid::uuid4()->toString().'.'.$extension;
        $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 150);
        $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'document.'.$extension;
        if (!is_dir($this->storageDirectory()) && !mkdir($this->storageDirectory(), 0775, true) && !is_dir($this->storageDirectory())) {
            throw new BusinessRuleViolation('Nie udało się przygotować katalogu na dokumenty.');
        }
        $file->move($this->storageDirectory(), $name);

        return ['storedName' => $name, 'fileName' => $original, 'mimeType' => $mime, 'size' => $size];
    }

    public function path(string $name): string
    {
        $base = realpath($this->storageDirectory());
        $path = realpath($this->storageDirectory().'/'.$name);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new PlanningResourceNotFound('Nie znaleziono pliku dokumentu.');
        }

        return $path;
    }

    public function discard(string $name): void
    {
        $path = $this->storageDirectory().'/'.basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
