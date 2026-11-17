<?php

declare(strict_types=1);

namespace App\Module\Support\Infrastructure;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Support\Application\Port\AttachmentStorage;
use App\Module\Support\Domain\TicketPolicy;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LocalAttachmentStorage implements AttachmentStorage
{
    private const array MIME = [
        'png' => ['image/png'],
        'jpeg' => ['image/jpeg', 'image/jpg'],
        'webp' => ['image/webp'],
    ];

    public function __construct(#[Autowire('%kernel.project_dir%/storage/support-%kernel.environment%')] private string $directory)
    {
    }

    public function accept(array $files): array
    {
        TicketPolicy::assertAttachmentCount(count($files));
        $stored = [];
        try {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    throw new BusinessRuleViolation('Nieprawidłowy załącznik.');
                }
                $stored[] = $this->storeOne($file);
            }
        } catch (\Throwable $error) {
            $this->discard(array_column($stored, 'storage_key'));
            throw $error;
        }

        return $stored;
    }

    public function discard(array $keys): void
    {
        foreach ($keys as $key) {
            if (!Uuid::isValid($key)) {
                continue;
            }
            $path = $this->directory.'/'.$key;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function path(string $key): string
    {
        if (!Uuid::isValid($key)) {
            throw new PlanningResourceNotFound('Nie znaleziono załącznika.');
        }
        $base = realpath($this->directory);
        $path = realpath($this->directory.'/'.$key);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new PlanningResourceNotFound('Nie znaleziono załącznika.');
        }

        return $path;
    }

    /** @return array{storage_key: string, original_name: string, mime_type: string, size_bytes: int} */
    private function storeOne(UploadedFile $file): array
    {
        if (!$file->isValid() || !is_int($file->getSize()) || $file->getSize() < 1 || $file->getSize() > TicketPolicy::MAX_ATTACHMENT_BYTES) {
            throw new BusinessRuleViolation('Nieprawidłowy obraz. Dozwolone są PNG, JPG, JPEG i WebP do 8 MB.');
        }
        $pathname = $file->getPathname();
        $kind = $this->sniff($pathname);
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $expected = match ($extension) {
            'png' => 'png',
            'jpg', 'jpeg' => 'jpeg',
            'webp' => 'webp',
            default => null,
        };
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($pathname);
        $allowed = $kind === null ? [] : self::MIME[$kind];
        if ($kind === 'webp') {
            $allowed[] = 'application/octet-stream';
        }
        if ($kind === null || $expected !== $kind || !is_string($mime) || !in_array($mime, $allowed, true)) {
            throw new BusinessRuleViolation('Dozwolone załączniki: PNG, JPG, JPEG i WebP. Rozszerzenie musi zgadzać się z zawartością.');
        }
        [$width, $height] = $this->pixels($pathname, $kind);
        if ($width < 1 || $height < 1 || $width * $height > 40000000) {
            throw new BusinessRuleViolation('Nieprawidłowy obraz albo obraz przekracza 40 megapikseli.');
        }
        $key = Uuid::uuid4()->toString();
        $size = $file->getSize();
        $name = $this->displayName($file->getClientOriginalName(), $extension);
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cannot create support storage.');
        }
        $stored = $file->move($this->directory, $key);
        chmod($stored->getPathname(), 0600);

        return [
            'storage_key' => $key,
            'original_name' => $name,
            'mime_type' => self::MIME[$kind][0],
            'size_bytes' => $size,
        ];
    }

    /** @return array{0: int, 1: int} */
    private function pixels(string $path, string $kind): array
    {
        $image = @getimagesize($path);
        if (is_array($image) && $image[0] > 0 && $image[1] > 0) {
            return [$image[0], $image[1]];
        }
        if ($kind === 'webp') {
            $size = $this->webpSize($path);
            if ($size !== null) {
                return $size;
            }
        }

        throw new BusinessRuleViolation('Nieprawidłowy obraz albo obraz przekracza 40 megapikseli.');
    }

    /** @return array{0: int, 1: int}|null */
    private function webpSize(string $path): ?array
    {
        $data = file_get_contents($path, false, null, 0, 40);
        if (!is_string($data) || strlen($data) < 30 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            return null;
        }
        $chunk = substr($data, 12, 4);
        if ($chunk === 'VP8X' && strlen($data) >= 30) {
            $width = unpack('V', substr($data, 24, 3)."\x00");
            $height = unpack('V', substr($data, 27, 3)."\x00");

            return [($width[1] ?? 0) + 1, ($height[1] ?? 0) + 1];
        }
        if ($chunk === 'VP8 ' && strlen($data) >= 30) {
            $width = unpack('v', substr($data, 26, 2));
            $height = unpack('v', substr($data, 28, 2));

            return [($width[1] ?? 0) & 0x3FFF, ($height[1] ?? 0) & 0x3FFF];
        }
        if ($chunk === 'VP8L' && strlen($data) >= 25) {
            $bytes = unpack('C4', substr($data, 21, 4));
            $bits = ($bytes[1] ?? 0) | (($bytes[2] ?? 0) << 8) | (($bytes[3] ?? 0) << 16) | (($bytes[4] ?? 0) << 24);

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1];
        }

        return null;
    }

    private function sniff(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = fread($handle, 16) ?: '';
        fclose($handle);
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }
        if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    private function displayName(string $original, string $extension): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '-', $base) ?? '';
        $suffix = '.'.($extension === 'jpeg' ? 'jpg' : $extension);
        if ($base === '' || !preg_match('/\.(png|jpe?g|webp)$/i', $base)) {
            $base = 'zalacznik'.$suffix;
        }

        return mb_substr($base, 0, 180);
    }
}
