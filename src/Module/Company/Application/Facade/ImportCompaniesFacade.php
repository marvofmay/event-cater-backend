<?php

namespace App\Module\Company\Application\Facade;

use App\Module\Company\Application\Command\Company\ImportCompaniesCommand;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportCompaniesFacade extends AbstractImportEnqueueFacade
{
    public function enqueue(UploadedFile $file): string
    {
        return $this->handle(
            file: $file,
            folder: 'companies',
            importKind: ImportKindEnum::IMPORT_COMPANIES,
            importCommand: fn (string $importUUID, string $userUUID) => new ImportCompaniesCommand($importUUID, $userUUID)
        );
    }
}
