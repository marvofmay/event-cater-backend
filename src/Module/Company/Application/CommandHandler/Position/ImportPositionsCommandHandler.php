<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Position;

use App\Module\Company\Application\Command\Position\ImportPositionsCommand;
use App\Module\Company\Application\Event\Position\PositionImportedEvent;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Infrastructure\Import\ImporterFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ImportPositionsCommandHandler
{
    public function __construct(
        private ImportReaderInterface $importReaderRepository,
        private ImporterFactory $importerFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportPositionsCommand $command): void
    {
        $import = $this->importReaderRepository->getImportByUUID($command->importUUID);

        $importer = $this->importerFactory->getImporter(ImportKindEnum::IMPORT_POSITIONS);
        $importer->setFilePath(sprintf('%s/%s', $import->getFile()->getFilePath(), $import->getFile()->getFileName()));

        $preparedRows = $importer->run($import);

        $this->eventDispatcher->dispatch(new PositionImportedEvent([
            ImportPositionsCommand::IMPORT_UUID => json_encode($preparedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
