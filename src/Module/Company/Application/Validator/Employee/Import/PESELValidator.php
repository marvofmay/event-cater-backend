<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Shared\Utils\PESELValidator as PESEL;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class PESELValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $pesel = trim((string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? ''));
        if ('' === $pesel) {
            return $this->messageService->get('employee.pesel.required', [], 'employees');
        }

        $flag = $row[EmployeeImportColumnEnum::IS_PESEL_FAKE->value] ?? null;
        $isFake = null === $flag || '' === trim((string) $flag)
            ? (($additionalData['employees'][$pesel] ?? null)?->isPeselFake() ?? false)
            : filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (null === $isFake) {
            return 'Kolumna isPeselFake musi zawierać 1 albo 0.';
        }

        $errorMessage = $isFake
            ? (preg_match('/^\d{11}$/D', $pesel) ? null : 'pesel.invalidLength')
            : PESEL::validate($pesel);
        if (null !== $errorMessage) {
            return $this->messageService->get($errorMessage, [], 'validators');
        }

        return null;
    }
}
