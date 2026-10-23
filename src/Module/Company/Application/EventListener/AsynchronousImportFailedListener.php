<?php

declare(strict_types=1);

namespace App\Module\Company\Application\EventListener;

use App\Module\Company\Application\Command\AsynchronousImportCommandInterface;
use App\Module\Company\Domain\Event\Company\CompanyImportedEvent;
use App\Module\Company\Domain\Event\Department\DepartmentImportedEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeImportedEvent;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportLogKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Domain\Interface\Import\ImportWriterInterface;
use App\Module\System\Domain\Service\ImportLog\ImportLogMultipleCreator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class AsynchronousImportFailedListener
{
    public function __construct(
        private ImportReaderInterface $importReader,
        private ImportWriterInterface $importWriter,
        private ImportLogMultipleCreator $importLogCreator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof AsynchronousImportCommandInterface) {
            return;
        }

        $this->handleFailure($message, $event->getThrowable());
    }

    public function handleFailure(AsynchronousImportCommandInterface $command, \Throwable $error): void
    {
        $import = $this->importReader->getImportByUuid($command->getImportUUID());
        if (null === $import) {
            return;
        }

        $import->markAsFailed();
        $this->importWriter->saveImportInDB($import);
        $this->importLogCreator->multipleCreate(
            $import,
            [$error->getMessage()],
            ImportLogKindEnum::IMPORT_ERROR
        );

        $event = match ($import->getKind()) {
            ImportKindEnum::IMPORT_COMPANIES => new CompanyImportedEvent([], $command->getImportUUID()),
            ImportKindEnum::IMPORT_DEPARTMENTS => new DepartmentImportedEvent([], $command->getImportUUID()),
            ImportKindEnum::IMPORT_EMPLOYEES => new EmployeeImportedEvent([], $command->getImportUUID()),
            default => null,
        };

        if (null !== $event) {
            // The existing payload providers read the persisted status and notify the import owner.
            $this->eventDispatcher->dispatch($event);
        }
    }
}
