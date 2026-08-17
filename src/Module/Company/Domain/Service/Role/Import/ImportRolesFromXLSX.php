<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Role\Import;

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
class ImportRolesFromXLSX extends XLSXIterator
{
    private array $roles = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportRolesReferenceLoader $importRolesReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        private readonly ImportRolesPreparer $importRolesPreparer,
        private readonly RolesImporter $rolesImporter,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[AutowireIterator(tag: 'app.role.import.validator')] private readonly iterable $importRolesValidators,
    ) {
        parent::__construct($this->translator);
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_ROLES->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $errorMessages = [];
        foreach ($this->importRolesValidators as $validator) {
            $error = $validator->validate($row, ['roles' => $this->roles,]);
            if (null !== $error) {
                $errorMessages[] = sprintf('%s - %s', $error, $this->messageService->get('row', [':index' => $index]));
            }
        }

        return $errorMessages;
    }

    public function run(Import $import): array
    {
        $this->importRolesReferenceLoader->preload($this->import());
        $this->roles = $this->importRolesReferenceLoader->roles;

        $errors = $this->validateBeforeImport();
        if (!empty($errors)) {
            $this->commandBus->dispatch(new UpdateImportCommand(import: $import, importStatusEnum: ImportStatusEnum::FAILED));
            $this->importLogMultipleCreator->multipleCreate(import: $import, data: $errors, enum: ImportLogKindEnum::IMPORT_ERROR);
            foreach ($errors as $error) {
                $this->eventBus->dispatch(
                    new LogFileEvent(
                        message: $this->messageService->get('role.import.error', [], 'positions').': '.$error,
                        level: LogLevel::ERROR,
                        channel: MonologChannelEnum::IMPORT
                    )
                );
            }
        } else {
            $preparedRows = $this->importRolesPreparer->prepare(rows: $this->import(), existingRoles: $this->roles);
            $this->rolesImporter->save(preparedRows: $preparedRows, existingRoles: $this->roles);
            $this->commandBus->dispatch(new UpdateImportCommand(import: $import, importStatusEnum: ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $this->import();
    }
}
