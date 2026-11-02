<?php

declare(strict_types=1);

namespace App\Module\CompanyNotice\Infrastructure\Storage;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CompanyNoticeMediaStorage
{
    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/storage/company-notices')] private string $directory,
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
            throw new BusinessRuleViolation('Nie udało się wgrać zdjęcia albo przekroczono limit serwera.');
        }
        $size = $file->getSize();
        if (!is_int($size) || $size < 1 || $size > 8 * 1024 * 1024) {
            throw new BusinessRuleViolation('Zdjęcie musi mieć od 1 B do 8 MiB.');
        }
        $mime = $file->getMimeType();
        if (!isset(self::EXTENSIONS[$mime]) || @getimagesize($file->getPathname()) === false) {
            throw new BusinessRuleViolation('Dozwolone formaty: JPEG, PNG, WebP.');
        }
        $extension = self::EXTENSIONS[$mime];
        $name = Uuid::uuid4()->toString().'.'.$extension;
        $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 150);
        $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'notice.'.$extension;
        if (!is_dir($this->storageDirectory()) && !mkdir($this->storageDirectory(), 0775, true) && !is_dir($this->storageDirectory())) {
            throw new BusinessRuleViolation('Nie udało się przygotować katalogu na zdjęcia ogłoszeń.');
        }
        $file->move($this->storageDirectory(), $name);

        return ['storedName' => $name, 'fileName' => $original, 'mimeType' => $mime, 'size' => $size];
    }

    public function path(string $name): string
    {
        $base = realpath($this->storageDirectory());
        $path = realpath($this->storageDirectory().'/'.$name);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new PlanningResourceNotFound('Nie znaleziono pliku zdjęcia.');
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
