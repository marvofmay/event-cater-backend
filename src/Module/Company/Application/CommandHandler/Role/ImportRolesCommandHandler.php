<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Role;

use App\Module\Company\Application\Command\Role\ImportRolesCommand;
use App\Module\Company\Application\Event\Role\RoleImportedEvent;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Infrastructure\Import\ImporterFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
readonly class ImportRolesCommandHandler
{
    public function __construct(
        private ImportReaderInterface $importReaderRepository,
        private ImporterFactory $importerFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportRolesCommand $command): void
    {
        $import = $this->importReaderRepository->getImportByUUID($command->importUUID);

        $importer = $this->importerFactory->getImporter(type: ImportKindEnum::IMPORT_ROLES);
        $importer->setFilePath(sprintf('%s/%s', $import->getFile()->getFilePath(), $import->getFile()->getFileName()));

        $preparedRows = $importer->run($import);

        $this->eventDispatcher->dispatch(new RoleImportedEvent([
            ImportRolesCommand::IMPORT_UUID => json_encode($preparedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
