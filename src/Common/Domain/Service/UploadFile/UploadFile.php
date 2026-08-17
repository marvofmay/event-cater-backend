<?php

declare(strict_types=1);

namespace App\Common\Domain\Service\UploadFile;

use App\Common\Domain\Enum\FileExtensionEnum;
use App\Common\Domain\Interface\UploadFileInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadFile implements UploadFileInterface
{
    private array $allowedExtensions = [
        FileExtensionEnum::PDF->value,
        FileExtensionEnum::CSV->value,
        FileExtensionEnum::PNG->value,
        FileExtensionEnum::XLSX->value,
        FileExtensionEnum::DOC->value,
        FileExtensionEnum::JPEG->value,
        FileExtensionEnum::JPG->value,
    ];

    private ?UploadedFile $uploadedFile = null;

    /**
     * @param string[] $expectedExtensions
     */
    public function __construct(
        private readonly string $uploadDir,
        private readonly array $expectedExtensions,
        private ?string $fileName = null,
    ) {
    }

    public function uploadFile(UploadedFile $file): void
    {
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0777, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('cannotCreateUploadDirectory');
        }

        $extension = strtolower(
            $file->guessExtension() ?: $file->getClientOriginalExtension()
        );

        if (!$this->isAllowedExtension($extension)) {
            throw new \DomainException('file.extension.notAllowed');
        }

        if (!$this->isExpectedExtension($extension)) {
            throw new \DomainException(
                sprintf(
                    'file.extension.invalid.expected.%s',
                    implode('_', $this->expectedExtensions)
                )
            );
        }

        $this->fileName ??= self::generateUniqueFileName($extension);

        try {
            $movedFile = $file->move($this->uploadDir, $this->fileName);

            $this->uploadedFile = new UploadedFile(
                $movedFile->getPathname(),
                $movedFile->getFilename(),
                $movedFile->getMimeType(),
                null,
                true
            );
        } catch (FileException) {
            throw new \RuntimeException('file.upload.failed');
        }
    }

    public function getUploadedFile(): ?UploadedFile
    {
        return $this->uploadedFile;
    }

    public function isAllowedExtension(string $extension): bool
    {
        return in_array($extension, $this->allowedExtensions, true);
    }

    public function isExpectedExtension(string $extension): bool
    {
        return in_array($extension, $this->expectedExtensions, true);
    }

    public function getFileName(): ?string
    {
        return $this->uploadedFile?->getFilename();
    }

    public static function generateUniqueFileName(string $extension): string
    {
        return uniqid('uploaded_', true) . '.' . $extension;
    }
}
