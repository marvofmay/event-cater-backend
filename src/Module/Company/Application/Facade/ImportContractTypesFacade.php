<?php

namespace App\Module\Company\Application\Facade;

use App\Module\Company\Application\Command\ContractType\ImportContractTypesCommand;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportContractTypesFacade extends AbstractImportFacade
{
    public function import(UploadedFile $file): array
    {
        return $this->handle(
            file: $file,
            folder: 'contract_types',
            importKind: ImportKindEnum::IMPORT_CONTRACT_TYPES,
            successMessage: $this->messageService->get('contractType.import.success', [], 'contract_types'),
            errorMessage: $this->messageService->get('contractType.import.error', [], 'contract_types'),
            importCommand: fn ($import) => $this->commandBus->dispatch(new ImportContractTypesCommand($import->getUUID()->toString()))
        );
    }
}
