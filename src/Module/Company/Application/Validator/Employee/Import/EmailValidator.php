<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Shared\Utils\EmailValidator as Email;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class EmailValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $email = trim((string) ($row[EmployeeImportColumnEnum::EMAIL->value] ?? ''));
        if ('' === $email) {
            return $this->messageService->get('employee.email.required', [], 'employees');
        }

        $errorMessage = Email::validate($email);
        if (null !== $errorMessage) {
            return $this->messageService->get($errorMessage, [], 'validators');
        }

        $pesel = (string) $row[EmployeeImportColumnEnum::PESEL->value] ?? null;
        if (array_key_exists($email, ($additionalData['emailsPESELs'] ?? [])) && $additionalData['emailsPESELs'][$email] !== $pesel) {
            return $this->messageService->get('employee.email.alreadyExists', [':email' => $email], 'employees');
        }

        return null;
    }
}
