<?php

namespace App\Module\Company\Application\Facade;

use App\Module\Company\Application\Command\Employee\ImportEmployeesCommand;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportEmployeesFacade extends AbstractImportEnqueueFacade
{
    public function enqueue(UploadedFile $file): string
    {
        return $this->handle(
            file: $file,
            folder: 'employees',
            importKind: ImportKindEnum::IMPORT_EMPLOYEES,
            importCommand: fn (string $importUUID, string $userUUID) => new ImportEmployeesCommand($importUUID, $userUUID)
        );
    }
}
