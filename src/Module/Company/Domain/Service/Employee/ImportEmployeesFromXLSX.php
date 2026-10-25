<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Common\XLSX\XLSXIterator;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\System\Application\Command\Import\UpdateImportCommand;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportLogKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use App\Module\System\Domain\Service\ImportLog\ImportLogMultipleCreator;
use App\Module\System\Domain\ValueObject\UserUUID;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag(name: 'app.importer')]
final class ImportEmployeesFromXLSX extends XLSXIterator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EmployeeReaderInterface $employeeReaderRepository,
        private readonly EmployeeAggregateCreator $employeeAggregateCreator,
        private readonly EmployeeAggregateUpdater $employeeAggregateUpdater,
        private readonly ImportEmployeesPreparer $importEmployeesPreparer,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportEmployeesReferenceLoader $importEmployeesReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[AutowireIterator(tag: 'app.employee.import.validator')] private readonly iterable $importEmployeesValidators,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($this->translator);
    }

    public function import(): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $row),
            parent::import()
        );
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_EMPLOYEES->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $this->importEmployeesReferenceLoader->preload($this->import());

        $departments = $this->importEmployeesReferenceLoader->departments;
        $positions = $this->importEmployeesReferenceLoader->positions;
        $contractTypes = $this->importEmployeesReferenceLoader->contractTypes;
        $roles = $this->importEmployeesReferenceLoader->roles;
        $employees = $this->importEmployeesReferenceLoader->employees;
        $emailsPESELs = $this->importEmployeesReferenceLoader->emailsPESELs;

        $errorMessages = [];
        foreach ($this->importEmployeesValidators as $validator) {
            $error = $validator->validate(
                $row,
                [
                    'departments' => $departments,
                    'positions' => $positions,
                    'contractTypes' => $contractTypes,
                    'roles' => $roles,
                    'employees' => $employees,
                    'emailsPESELs' => $emailsPESELs,
                ]
            );
            if (null !== $error) {
                $errorMessages[] = sprintf('%s - %s', $error, $this->messageService->get('row', [':index' => $index]));
            }
        }

        return $errorMessages;
    }

    private function resolveParentUUID(array $row, array $peselMap): ?EmployeeUUID
    {
        $parentRaw = $row[EmployeeImportColumnEnum::PARENT_EMPLOYEE_PESEL->value] ?? null;
        if (null === $parentRaw) {
            return null;
        }

        $parentPESEL = trim((string) $parentRaw);
        if ('' === $parentPESEL) {
            return null;
        }

        if (isset($peselMap[$parentPESEL])) {
            return $peselMap[$parentPESEL];
        }

        $existingParentEmployee = $this->entityReferenceCache->get(
            Employee::class,
            $parentPESEL,
            fn (string $parentPESEL) => $this->employeeReaderRepository->getEmployeeByPESEL($parentPESEL)
        );

        return EmployeeUUID::fromString($existingParentEmployee->getUUID()->toString());
    }

    public function run(Import $import, UserInterface $loggedUser): array
    {
        $errors = $this->validateBeforeImport();

        if (!empty($errors)) {
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::FAILED));
            $this->importLogMultipleCreator->multipleCreate($import, $errors, ImportLogKindEnum::IMPORT_ERROR);
            foreach ($errors as $error) {
                $this->eventBus->dispatch(
                    new LogFileEvent($this->messageService->get('employee.import.error', [], 'employees').': '.$error)
                );
            }

            return $this->import();
        } else {
            [$preparedRows, $peselMap] = $this->importEmployeesPreparer->prepare($this->import());

            $loggedUserUUID = $loggedUser->getUUID()->toString();
            $loggedUserUUID = UserUUID::fromString($loggedUserUUID);

            foreach ($preparedRows as $row) {
                $parentUUID = $this->resolveParentUUID($row, $peselMap);

                $pesel = trim((string) $row[EmployeeImportColumnEnum::PESEL->value]);
                $uuid = $peselMap[$pesel];

                if (!$row[EmployeeImportColumnEnum::DYNAMIC_IS_EMPLOYEE_WITH_PESEL_ALREADY_EXISTS->value]) {
                    $this->employeeAggregateCreator->create($row, $uuid, $parentUUID, $loggedUserUUID);
                } else {
                    $this->employeeAggregateUpdater->update($row, $parentUUID, $loggedUserUUID);
                }
            }

            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $preparedRows;
    }
}
