<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Infrastructure\Cache\EntityReferenceCache;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;

final readonly class ImportEmployeesPreparer
{
    public function __construct(
        private EmployeeReaderInterface $employeeReaderRepository,
        private EntityReferenceCache $entityReferenceCache,
    ) {
    }

    public function prepare(iterable $rows): array
    {
        $peselMap = [];
        $preparedRows = [];

        foreach ($rows as $row) {
            $pesel = trim((string) $row[EmployeeImportColumnEnum::PESEL->value]);
            $existingEmployee = $this->entityReferenceCache->get(
                Employee::class,
                $pesel,
                fn (string $pesel) => $this->employeeReaderRepository->getEmployeeByPESEL($pesel)
            );

            $fakeFlag = $row[EmployeeImportColumnEnum::IS_PESEL_FAKE->value] ?? null;
            $row[EmployeeImportColumnEnum::IS_PESEL_FAKE->value] = null === $fakeFlag || '' === trim((string) $fakeFlag)
                ? ($existingEmployee?->isPeselFake() ?? false)
                : filter_var($fakeFlag, FILTER_VALIDATE_BOOLEAN);

            $row[EmployeeImportColumnEnum::DYNAMIC_IS_EMPLOYEE_WITH_PESEL_ALREADY_EXISTS->value] = null !== $existingEmployee;

            if (!isset($peselMap[$pesel])) {
                $peselMap[$pesel] = $existingEmployee
                    ? EmployeeUUID::fromString($existingEmployee->getUUID()->toString())
                    : EmployeeUUID::generate();
            }

            $row[EmployeeImportColumnEnum::DYNAMIC_AGGREGATE_UUID->value] = $peselMap[$pesel]->toString();
            $preparedRows[] = $row;
        }

        return [$preparedRows, $peselMap];
    }
}
