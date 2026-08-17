<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\ContractType\Import;

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
class ImportContractTypesFromXLSX extends XLSXIterator
{
    private array $contractTypes = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportContractTypesReferenceLoader $importContractTypesReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        private readonly ImportContractTypesPreparer $importContractTypesPreparer,
        private readonly ContractTypesImporter $contractTypesImporter,
        #[AutowireIterator(tag: 'app.contract_type.import.validator')] private readonly iterable $importContractTypesValidators,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($this->translator);
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_CONTRACT_TYPES->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $errorMessages = [];

        foreach ($this->importContractTypesValidators as $validator) {
            $error = $validator->validate(
                $row,
                [
                    'contractTypes' => $this->contractTypes,
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
        $this->importContractTypesReferenceLoader->preload($this->import());
        $this->contractTypes = $this->importContractTypesReferenceLoader->contractTypes;

        $errors = $this->validateBeforeImport();
        if (!empty($errors)) {
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::FAILED));
            $this->importLogMultipleCreator->multipleCreate($import, $errors, ImportLogKindEnum::IMPORT_ERROR);
            foreach ($errors as $error) {
                $this->eventBus->dispatch(
                    new LogFileEvent(
                        $this->messageService->get('contractType.import.error', [], 'contract_types').': '.$error,
                        LogLevel::ERROR,
                        MonologChannelEnum::IMPORT
                    )
                );
            }
        } else {
            $preparedRows = $this->importContractTypesPreparer->prepare($this->import(), $this->contractTypes);
            $this->contractTypesImporter->save(preparedRows: $preparedRows, existingContractTypes: $this->contractTypes);
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $this->import();
    }
}
