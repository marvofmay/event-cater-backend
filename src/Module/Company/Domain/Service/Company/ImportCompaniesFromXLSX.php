<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Company;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Common\XLSX\XLSXIterator;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Enum\CompanyImportColumnEnum;
use App\Module\Company\Domain\Interface\Company\CompanyReaderInterface;
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
final class ImportCompaniesFromXLSX extends XLSXIterator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CompanyReaderInterface $companyReaderRepository,
        private readonly CompanyAggregateCreator $companyAggregateCreator,
        private readonly CompanyAggregateUpdater $companyAggregateUpdater,
        private readonly ImportCompaniesPreparer $importCompaniesPreparer,
        private readonly ImportLogMultipleCreator $importLogMultipleCreator,
        private readonly MessageService $messageService,
        private readonly ImportCompaniesReferenceLoader $importCompaniesReferenceLoader,
        private readonly EntityReferenceCache $entityReferenceCache,
        #[AutowireIterator(tag: 'app.company.import.validator')] private readonly iterable $importCompaniesValidators,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($this->translator);
    }

    public function getType(): string
    {
        return ImportKindEnum::IMPORT_COMPANIES->value;
    }

    public function validateRow(array $row, int $index): array
    {
        $this->importCompaniesReferenceLoader->preload($this->import());
        $industries = $this->importCompaniesReferenceLoader->industries;
        $companies = $this->importCompaniesReferenceLoader->companies;
        $emailsNIPs = $this->importCompaniesReferenceLoader->emailsNIPs;

        $errorMessages = [];
        foreach ($this->importCompaniesValidators as $validator) {
            $error = $validator->validate(
                $row,
                [
                    'industries' => $industries,
                    'companies' => $companies,
                    'emailsNIPs' => $emailsNIPs,
                ]
            );
            if (null !== $error) {
                $errorMessages[] = sprintf('%s - %s', $error, $this->messageService->get('row', [':index' => $index]));
            }
        }

        return $errorMessages;
    }

    private function resolveParentUUID(array $row, array $nipMap): ?CompanyUUID
    {
        $parentRaw = $row[CompanyImportColumnEnum::PARENT_COMPANY_NIP->value] ?? null;
        if (null === $parentRaw) {
            return null;
        }

        $parentNIP = trim((string) $parentRaw);
        if ('' === $parentNIP) {
            return null;
        }

        if (isset($nipMap[$parentNIP])) {
            return $nipMap[$parentNIP];
        }

        $existingParentCompany = $this->entityReferenceCache->get(
            Company::class,
            $parentNIP,
            fn (string $parentNIP) => $this->companyReaderRepository->getCompanyByNIP($parentNIP)
        );

        return CompanyUUID::fromString($existingParentCompany->getUUID()->toString());
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
                        $this->messageService->get('company.import.error', [], 'companies').': '.$error,
                        LogLevel::ERROR,
                        MonologChannelEnum::IMPORT
                    )
                );
            }

            return $this->import();
        } else {
            [$preparedRows, $nipMap] = $this->importCompaniesPreparer->prepare($this->import());

            $loggedUserUUID = $loggedUser->getUUID()->toString();
            $loggedUserUUID = UserUUID::fromString($loggedUserUUID);

            foreach ($preparedRows as $row) {
                $parentUUID = $this->resolveParentUUID($row, $nipMap);

                $nip = trim((string) $row[CompanyImportColumnEnum::NIP->value]);
                $uuid = $nipMap[$nip];

                if (!$row[CompanyImportColumnEnum::DYNAMIC_IS_COMPANY_WITH_NIP_ALREADY_EXISTS->value]) {
                    $this->companyAggregateCreator->create($row, $uuid, $parentUUID, $loggedUserUUID);
                } else {
                    $this->companyAggregateUpdater->update($row, $parentUUID, $loggedUserUUID);
                }
            }
            $this->commandBus->dispatch(new UpdateImportCommand($import, ImportStatusEnum::DONE));
        }
        $this->entityReferenceCache->clear();

        return $preparedRows;
    }
}
