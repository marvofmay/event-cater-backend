<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Department;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Common\XLSX\XLSXIterator;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Enum\DepartmentImportColumnEnum;
use App\Module\Company\Domain\Interface\Department\DepartmentReaderInterface;
use App\Module\System\Application\Command\Import\UpdateImportCommand;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportLogKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use App\Module\System\Domain\Service\ImportLog\ImportLogMultipleCreator;
use App\Module\System\Domain\ValueObject\UserUUID;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag(name: 'app.importer')]
final class ImportDepartmentsFromXLSX extends XLSXIterator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DepartmentReaderInterface $departmentReaderRepository,
        private readonly DepartmentAggregateCreator $departmentAggregateCreator,
        private readonly DepartmentAggregateUpdater $departmentAggregateUpdater,
        private readonly ImportDepartmentsPreparer $importDepartmentsPreparer,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportDepartmentsReferenceLoader $importDepartmentsReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        #[AutowireIterator(tag: 'app.department.import.validator')] private readonly iterable $importDepartmentsValidators,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($this->translator);
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_DEPARTMENTS->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $this->importDepartmentsReferenceLoader->preload($this->import());
        $companies = $this->importDepartmentsReferenceLoader->companies;
        $departments = $this->importDepartmentsReferenceLoader->departments;
        $emailsInternalCodes = $this->importDepartmentsReferenceLoader->emailsInternalCodes;

        $errorMessages = [];
        foreach ($this->importDepartmentsValidators as $validator) {
            $error = $validator->validate(
                $row,
                [
                    'companies' => $companies,
                    'departments' => $departments,
                    'emailsInternalCodes' => $emailsInternalCodes,
                ]
            );
            if (null !== $error) {
                $errorMessages[] = sprintf('%s - %s', $error, $this->messageService->get('row', [':index' => $index]));
            }
        }

        return $errorMessages;
    }

    private function resolveParentUUID(array $row, array $internalCodeMap): ?DepartmentUUID
    {
        $parentRaw = $row[DepartmentImportColumnEnum::PARENT_DEPARTMENT_INTERNAL_CODE->value] ?? null;
        if (null === $parentRaw) {
            return null;
        }

        $parentInternalCode = trim((string) $parentRaw);
        if ('' === $parentInternalCode) {
            return null;
        }

        if (isset($internalCodeMap[$parentInternalCode])) {
            return $internalCodeMap[$parentInternalCode];
        }

        $existingParentDepartment = $this->entityReferenceCache->get(
            Department::class,
            $parentInternalCode,
            fn (string $parentInternalCode) => $this->departmentReaderRepository->getDepartmentByInternalCode($parentInternalCode)
        );

        return DepartmentUUID::fromString($existingParentDepartment->getUUID()->toString());
    }

    public function run(Import $import, UserInterface $loggedUser): array
    {
        $errors = $this->validateBeforeImport();

        if (!empty($errors)) {
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::FAILED));
            $this->importLogMultipleCreator->multipleCreate($import, $errors, ImportLogKindEnum::IMPORT_ERROR);
            foreach ($errors as $error) {
                $this->eventBus->dispatch(
                    new LogFileEvent(
                        $this->messageService->get('department.import.error', [], 'departments').': '.$error,
                        LogLevel::ERROR,
                        MonologChannelEnum::IMPORT
                    )
                );
            }

            return $this->import();
        } else {
            [$preparedRows, $internalCodeMap] = $this->importDepartmentsPreparer->prepare($this->import());

            $loggedUserUUID = $loggedUser->getUUID()->toString();
            $loggedUserUUID = UserUUID::fromString($loggedUserUUID);

            foreach ($preparedRows as $row) {
                $parentUUID = $this->resolveParentUUID($row, $internalCodeMap);

                $internalCode = trim((string) $row[DepartmentImportColumnEnum::DEPARTMENT_INTERNAL_CODE->value]);
                $uuid = $internalCodeMap[$internalCode];

                if (!$row[DepartmentImportColumnEnum::DYNAMIC_IS_DEPARTMENT_WITH_INTERNAL_CODE_ALREADY_EXISTS->value]) {
                    $this->departmentAggregateCreator->create($row, $uuid, $parentUUID, $loggedUserUUID);
                } else {
                    $this->departmentAggregateUpdater->update($row, $parentUUID, $loggedUserUUID);
                }
            }

            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $preparedRows;
    }
}
