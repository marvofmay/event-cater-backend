<?php

namespace App\Module\Company\Application\Facade;

use App\Module\Company\Application\Command\Department\ImportDepartmentsCommand;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportDepartmentsFacade extends AbstractImportEnqueueFacade
{
    public function enqueue(UploadedFile $file): string
    {
        return $this->handle(
            file: $file,
            folder: 'departments',
            importKind: ImportKindEnum::IMPORT_DEPARTMENTS,
            importCommand: fn (string $importUUID, string $userUUID) => new ImportDepartmentsCommand($importUUID, $userUUID)
        );
    }
}
