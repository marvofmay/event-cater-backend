<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Industry\Import;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Common\XLSX\XLSXIterator;
use App\Module\System\Application\Command\Import\UpdateImportCommand;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportLogKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use App\Module\System\Domain\Service\ImportLog\ImportLogMultipleCreator;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.importer')]
class ImportIndustriesFromXLSX extends XLSXIterator
{
    private array $industries = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportIndustriesReferenceLoader $importIndustriesReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        private readonly ImportIndustriesPreparer $importIndustriesPreparer,
        private readonly IndustriesImporter $industriesImporter,
        #[AutowireIterator(tag: 'app.industry.import.validator')] private readonly iterable $importIndustriesValidators,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($this->translator);
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_INDUSTRIES->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $errorMessages = [];

        foreach ($this->importIndustriesValidators as $validator) {
            $error = $validator->validate(
                $row,
                [
                    'industries' => $this->industries,
                ]
            );
            if (null !== $error) {
                $errorMessages[] = sprintf('%s - %s', $error, $this->messageService->get('row', [':index' => $index]));
            }
        }

        return $errorMessages;
    }

    public function run(Import $import): array
    {
        $this->importIndustriesReferenceLoader->preload($this->import());
        $this->industries = $this->importIndustriesReferenceLoader->industries;

        $errors = $this->validateBeforeImport();
        if (!empty($errors)) {
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::FAILED));
            $this->importLogMultipleCreator->multipleCreate($import, $errors, ImportLogKindEnum::IMPORT_ERROR);
            foreach ($errors as $error) {
                $this->eventBus->dispatch(
                    new LogFileEvent(
                        $this->messageService->get('industry.import.error', [], 'positions').': '.$error,
                        LogLevel::ERROR,
                        MonologChannelEnum::IMPORT
                    )
                );
            }
        } else {
            $preparedRows = $this->importIndustriesPreparer->prepare($this->import(), $this->industries);
            $this->industriesImporter->save(preparedRows: $preparedRows, existingIndustries: $this->industries);

            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $this->import();
    }
}
