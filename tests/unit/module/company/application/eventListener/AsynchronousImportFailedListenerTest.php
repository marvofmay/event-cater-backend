<?php

declare(strict_types=1);

namespace App\tests\unit\module\company\application\eventListener;

use App\Module\Company\Application\Command\Company\ImportCompaniesCommand;
use App\Module\Company\Application\EventListener\AsynchronousImportFailedListener;
use App\Module\Company\Domain\Event\Company\CompanyImportedEvent;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportLogKindEnum;
use App\Module\System\Domain\Interface\Import\ImportReaderInterface;
use App\Module\System\Domain\Interface\Import\ImportWriterInterface;
use App\Module\System\Domain\Interface\ImportLog\ImportLogWriterInterface;
use App\Module\System\Domain\Service\ImportLog\ImportLogMultipleCreator;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AsynchronousImportFailedListenerTest extends TestCase
{
    public function testItPersistsTerminalFailureLogAndDispatchesOwnerNotificationEvent(): void
    {
        $importUUID = '018f874d-1320-7a10-b44c-16a4ac6f5a55';
        $command = new ImportCompaniesCommand($importUUID, 'user-uuid');
        $error = new \RuntimeException('Broken spreadsheet');

        $import = $this->createMock(Import::class);
        $import->expects(self::once())->method('markAsFailed');
        $import->method('getKind')->willReturn(ImportKindEnum::IMPORT_COMPANIES);

        $reader = $this->createMock(ImportReaderInterface::class);
        $reader->expects(self::once())->method('getImportByUuid')->with($importUUID)->willReturn($import);

        $writer = $this->createMock(ImportWriterInterface::class);
        $writer->expects(self::once())->method('saveImportInDB')->with($import);

        $logWriter = $this->createMock(ImportLogWriterInterface::class);
        $logWriter->expects(self::once())
            ->method('saveImportLogsInDB')
            ->with(self::callback(static function (Collection $logs) use ($import): bool {
                if (1 !== $logs->count()) {
                    return false;
                }

                $log = $logs->first();

                return $log->getImport() === $import
                    && ImportLogKindEnum::IMPORT_ERROR === $log->getKind()
                    && ['Broken spreadsheet'] === $log->getData();
            }));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $event): bool => $event instanceof CompanyImportedEvent
                && $event->importUUID === $importUUID
                && [] === $event->rows));

        $listener = new AsynchronousImportFailedListener(
            $reader,
            $writer,
            new ImportLogMultipleCreator($logWriter),
            $eventDispatcher
        );

        $listener->handleFailure($command, $error);
    }
}
