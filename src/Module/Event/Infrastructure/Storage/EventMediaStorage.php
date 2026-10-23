<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Storage;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class EventMediaStorage
{
    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    public function __construct(#[Autowire('%kernel.project_dir%/storage/event-media')] private string $directory, private ?\App\Module\Saas\Infrastructure\TenantContext $tenantContext = null)
    {
    }

    private function storageDirectory(): string
    {
        $tenant = $this->tenantContext?->current();
        return !$tenant || $tenant['slug'] === 'default' ? $this->directory : $this->directory.'/'.$tenant['uuid'];
    }

    public function store(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new BusinessRuleViolation('Upload failed or exceeds the server upload limit.');
        }
        $size = $file->getSize();
        if (!is_int($size) || $size < 1 || $size > 100 * 1024 * 1024) {
            throw new BusinessRuleViolation('Media size must be between 1 byte and 100 MiB.');
        }
        $mime = $file->getMimeType();
        if (!isset(self::EXTENSIONS[$mime])) {
            throw new BusinessRuleViolation('Allowed: JPEG, PNG, WebP, GIF, MP4, WebM, MOV; maximum 100 MiB.');
        }
        if (str_starts_with($mime, 'image/') && ($size > 20 * 1024 * 1024 || @getimagesize($file->getPathname()) === false)) {
            throw new BusinessRuleViolation('Invalid image or image exceeds 20 MiB.');
        }
        $extension = self::EXTENSIONS[$mime];
        $name = Uuid::uuid4()->toString().'.'.$extension;
        $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 150);
        $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'event-media.'.$extension;
        $file->move($this->storageDirectory(), $name);
        return ['storedName' => $name, 'fileName' => $original, 'extension' => $extension, 'mimeType' => $mime, 'size' => $size];
    }

    public function path(string $name): string
    {
        $base = realpath($this->storageDirectory());
        $path = realpath($this->storageDirectory().'/'.$name);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new PlanningResourceNotFound('Media file not found.');
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
