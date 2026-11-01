<?php

declare(strict_types=1);

namespace App\Module\FileManager\Infrastructure;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\FileManager\Application\Port\PrivateFileStorage;
use App\Module\FileManager\Domain\FileName;
use App\Module\Saas\Infrastructure\TenantContext;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LocalPrivateFileStorage implements PrivateFileStorage
{
    public const int MAX_BYTES = 20 * 1024 * 1024;
    private const array TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/storage/file-manager-%kernel.environment%')] private string $directory,
        private TenantContext $tenants,
    ) {
    }

    private function directory(): string
    {
        $uuid = $this->tenants->current()['uuid'];
        if (!Uuid::isValid($uuid)) throw new \LogicException('Invalid storage tenant.');
        return $this->directory.'/'.$uuid;
    }

    public function store(UploadedFile $file, string $name): array
    {
        FileName::validate($name);
        if (!$file->isValid() || !is_int($file->getSize()) || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw new BusinessRuleViolation('Nieprawidłowy plik. Maksymalny rozmiar to 20 MiB, plik nie może być pusty.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $file->getMimeType();
        if (!isset(self::TYPES[$extension]) || !in_array($mime, self::TYPES[$extension], true)) {
            throw new BusinessRuleViolation('Dozwolone pliki: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, WebP, TXT i CSV. Rozszerzenie musi zgadzać się z zawartością.');
        }
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $this->validateOffice($file->getPathname(), $extension);
            $mime = $extension === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        if (str_starts_with($mime, 'image/')) {
            $image = @getimagesize($file->getPathname());
            if (!$image || $image[0] * $image[1] > 40000000) throw new BusinessRuleViolation('Nieprawidłowy obraz lub obraz przekracza 40 megapikseli.');
        }
        $blob = Uuid::uuid4()->toString();
        $size = $file->getSize();
        $directory = $this->directory();
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Cannot create private storage.');
        $stored = $file->move($directory, $blob);
        chmod($stored->getPathname(), 0600);
        return ['blob_name' => $blob, 'mime_type' => $mime, 'size' => $size];
    }

    private function validateOffice(string $path, string $extension): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new BusinessRuleViolation('Nieprawidłowy dokument Office.');
        try {
            $required = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName($required) === false) throw new BusinessRuleViolation('Nieprawidłowy dokument Office.');
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entry = $zip->statIndex($index);
                if ($entry === false) throw new BusinessRuleViolation('Nieprawidłowy dokument Office.');
                $total += $entry['size'];
                if ($total > 100 * 1024 * 1024 || $entry['size'] > max(1, $entry['comp_size']) * 200 || preg_match('#(^/|\\\\|(^|/)\.\.(/|$)|vbaproject|/embeddings/|/activex/)#i', $entry['name']) || ($entry['encryption_method'] ?? 0) !== 0) {
                    throw new BusinessRuleViolation('Dokument zawiera niedozwolone elementy lub przekracza limity rozpakowanego pliku.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function path(string $blob): string
    {
        if (!Uuid::isValid($blob)) throw new PlanningResourceNotFound('Nie znaleziono pliku.');
        $base = realpath($this->directory());
        $path = realpath($this->directory().'/'.$blob);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) throw new PlanningResourceNotFound('Nie znaleziono pliku.');
        return $path;
    }

    public function discard(string $blob): void
    {
        if (!Uuid::isValid($blob)) throw new \LogicException('Invalid storage key.');
        $path = $this->directory().'/'.$blob;
        if (is_file($path) && !unlink($path)) throw new \RuntimeException('Cannot remove private blob.');
    }
}
