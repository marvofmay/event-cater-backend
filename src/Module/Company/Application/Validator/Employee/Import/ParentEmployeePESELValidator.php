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

        return null;
    }
}
