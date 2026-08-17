<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\ContractType;

use App\Module\Company\Application\Command\ContractType\ImportContractTypesCommand;
use App\Module\Company\Application\Event\ContractType\ContractTypeImportedEvent;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Infrastructure\Import\ImporterFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ImportContractTypesCommandHandler
{
    public function __construct(
        private ImportReaderInterface $importReaderRepository,
        private ImporterFactory $importerFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportContractTypesCommand $command): void
    {
        $import = $this->importReaderRepository->getImportByUUID($command->importUUID);

        $importer = $this->importerFactory->getImporter(ImportKindEnum::IMPORT_CONTRACT_TYPES);
        $importer->setFilePath(sprintf('%s/%s', $import->getFile()->getFilePath(), $import->getFile()->getFileName()));

        $preparedRows = $importer->run($import);

        $this->eventDispatcher->dispatch(new ContractTypeImportedEvent([
            ImportContractTypesCommand::IMPORT_UUID => json_encode($preparedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
