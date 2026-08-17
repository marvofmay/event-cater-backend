<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Industry;

use App\Module\Company\Application\Command\Industry\ImportIndustriesCommand;
use App\Module\Company\Application\Event\Industry\IndustryImportedEvent;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Infrastructure\Import\ImporterFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ImportIndustriesCommandHandler
{
    public function __construct(
        private ImportReaderInterface $importReaderRepository,
        private ImporterFactory $importerFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportIndustriesCommand $command): void
    {
        $import = $this->importReaderRepository->getImportByUUID($command->importUUID);

        $importer = $this->importerFactory->getImporter(ImportKindEnum::IMPORT_INDUSTRIES);
        $importer->setFilePath(sprintf('%s/%s', $import->getFile()->getFilePath(), $import->getFile()->getFileName()));

        $preparedRows = $importer->run($import);

        $this->eventDispatcher->dispatch(new IndustryImportedEvent([
            ImportIndustriesCommand::IMPORT_UUID => json_encode($preparedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
