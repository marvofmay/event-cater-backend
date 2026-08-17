<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Company;

use App\Common\Domain\Entity\EventStore;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Module\Company\Application\Command\Company\ImportCompaniesCommand;
use App\Module\Company\Domain\Aggregate\Company\CompanyAggregate;
use App\Module\Company\Domain\Event\Company\CompanyImportedEvent;
use App\Module\Company\Domain\Interface\User\UserReaderInterface;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Infrastructure\Import\ImporterFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ImportCompaniesCommandHandler
{
    public function __construct(
        private ImportReaderInterface $importReaderRepository,
        private UserReaderInterface $userReaderRepository,
        private EventStoreCreator $eventStoreCreator,
        private SerializerInterface $serializer,
        private ImporterFactory $importerFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ImportCompaniesCommand $command): void
    {
        $import = $this->importReaderRepository->getImportByUUID($command->importUUID);
        $loggedUser = $this->userReaderRepository->getUserByUUID($command->loggedUserUUID);

        $importer = $this->importerFactory->getImporter(ImportKindEnum::IMPORT_COMPANIES);
        $importer->setFilePath(sprintf('%s/%s', $import->getFile()->getFilePath(), $import->getFile()->getFileName()));

        $preparedRows = $importer->run($import, $loggedUser);

        $multiEvent = new CompanyImportedEvent($preparedRows, $command->importUUID);
        $this->eventStoreCreator->create(
            new EventStore(
                Uuid::uuid4()->toString(),
                $multiEvent::class,
                CompanyAggregate::class,
                $this->serializer->serialize($multiEvent, 'json'),
                $import->getUser(),
            )
        );

        $this->eventDispatcher->dispatch($multiEvent);
    }
}
