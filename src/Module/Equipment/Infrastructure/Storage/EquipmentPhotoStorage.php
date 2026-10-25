<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Storage;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Saas\Infrastructure\TenantContext;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class EquipmentPhotoStorage
{
    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(#[Autowire('%kernel.project_dir%/storage/equipment-photos')] private string $directory, private ?TenantContext $tenantContext = null)
    {
    }

    private function directory(string $equipmentUuid): string
    {
        $tenant = $this->tenantContext?->current();
        $base = !$tenant || $tenant['slug'] === 'default' ? $this->directory : $this->directory.'/'.$tenant['uuid'];
        return $base.'/'.Uuid::fromString($equipmentUuid)->toString();
    }

    public function store(string $equipmentUuid, UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new BusinessRuleViolation('Nie udało się przesłać zdjęcia lub przekroczono limit serwera.');
        }
        $size = $file->getSize();
        $mime = $file->getMimeType();
        if (!is_int($size) || $size < 1 || $size > 20 * 1024 * 1024 || !isset(self::EXTENSIONS[$mime]) || @getimagesize($file->getPathname()) === false) {
            throw new BusinessRuleViolation('Dodaj poprawne zdjęcie JPEG, PNG, WebP lub GIF o wielkości do 20 MiB.');
        }
        $storedName = Uuid::uuid4()->toString().'.'.self::EXTENSIONS[$mime];
        $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 150);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'zdjecie.'.self::EXTENSIONS[$mime];
        $file->move($this->directory($equipmentUuid), $storedName);
        return ['name' => $name, 'storedName' => $storedName, 'mimeType' => $mime, 'size' => $size];
    }

    public function path(string $equipmentUuid, string $name): string
    {
        $base = realpath($this->directory($equipmentUuid));
        $path = realpath($this->directory($equipmentUuid).'/'.basename($name));
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new PlanningResourceNotFound('Nie znaleziono pliku zdjęcia.');
        }
        return $path;
    }

    public function discard(string $equipmentUuid, string $name): void
    {
        $path = $this->directory($equipmentUuid).'/'.basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function discardAll(string $equipmentUuid): void
    {
        $directory = $this->directory($equipmentUuid);
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
