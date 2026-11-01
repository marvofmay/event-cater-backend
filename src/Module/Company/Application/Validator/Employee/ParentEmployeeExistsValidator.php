<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.employee.create.validator')]
#[AutoconfigureTag('app.employee.update.validator')]
final readonly class ParentEmployeeExistsValidator
{
    public function __construct(private EmployeeReaderInterface $employeeReaderRepository, private TranslatorInterface $translator)
    {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return property_exists($data, 'parentEmployeeUUID') && null !== $data->parentEmployeeUUID;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        if (!property_exists($data, 'parentEmployeeUUID')) {
            return;
        }

        $parentUUID = $data->parentEmployeeUUID;
        $employeeExists = $this->employeeReaderRepository->isEmployeeWithUUIDExists($parentUUID);
        if (!$employeeExists) {
            throw new \Exception($this->translator->trans('employee.uuid.notExists', [':uuid' => $parentUUID], 'employees'), Response::HTTP_CONFLICT);
        }
        $parent = $this->employeeReaderRepository->getEmployeeByUUID($parentUUID);
        if ($parent->getCompany()->getUUID()->toString() !== $data->companyUUID) throw new \Exception('Przełożony musi należeć do tej samej firmy.', 422);
        $seen = [];
        while ($parent !== null) {
            $uuid = $parent->getUUID()->toString();
            if ($uuid === ($data->employeeUUID ?? null) || isset($seen[$uuid]) || count($seen) >= 64) throw new \Exception('Powiązanie przełożonego tworzy cykl lub przekracza 64 poziomy.', 422);
            $seen[$uuid] = true;
            $parent = $parent->getParentEmployee();
        }
    }
}
