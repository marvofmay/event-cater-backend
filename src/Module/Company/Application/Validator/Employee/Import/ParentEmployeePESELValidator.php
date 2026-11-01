<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Shared\Utils\PESELValidator as PESEL;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class ParentEmployeePESELValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $parentEmployeePESEL = $row[EmployeeImportColumnEnum::PARENT_EMPLOYEE_PESEL->value] ?? null;
        if (empty($parentEmployeePESEL)) {
            return null;
        }

        $parentEmployeePESEL = trim((string) $parentEmployeePESEL);
        $errorMessage = preg_match('/^\d{11}$/D', $parentEmployeePESEL) ? null : 'pesel.invalidLength';
        if (null !== $errorMessage) {
            return $this->messageService->get($errorMessage, [], 'validators');
        }
        $records = $additionalData['supervisorRows'] ?? [];
        $employees = $additionalData['employees'] ?? [];
        if (!isset($records[$parentEmployeePESEL]) && !isset($employees[$parentEmployeePESEL])) return 'Nie znaleziono przełożonego wskazanego numerem PESEL.';
        $department = $additionalData['departments'][(string) ($row[EmployeeImportColumnEnum::DEPARTMENT_UUID->value] ?? '')] ?? null;
        $parentDepartment = isset($records[$parentEmployeePESEL]) ? ($additionalData['departments'][$records[$parentEmployeePESEL]['department']] ?? null) : ($employees[$parentEmployeePESEL] ?? null)?->getDepartment();
        if ($department && $parentDepartment && $department->getCompany()->getUUID()->toString() !== $parentDepartment->getCompany()->getUUID()->toString()) return 'Przełożony musi należeć do tej samej firmy.';
        $seen = [(string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? '') => true];
        $current = $parentEmployeePESEL;
        $entity = $employees[$current] ?? null;
        while ($current !== '') {
            if (isset($seen[$current]) || count($seen) >= 64) return 'Powiązania przełożonych tworzą cykl lub przekraczają 64 poziomy.';
            $seen[$current] = true;
            if (isset($records[$current])) {
                $current = $records[$current]['parent'];
                $entity = $employees[$current] ?? null;
            } else {
                $entity = $entity?->getParentEmployee();
                $current = $entity?->getPESEL() ?? '';
            }
        }

        return null;
    }
}
