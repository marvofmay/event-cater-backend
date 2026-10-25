<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Department\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Shared\Utils\EmailValidator as Email;
use App\Module\Company\Domain\Enum\DepartmentImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.department.import.validator')]
final readonly class EmailValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $email = trim((string) ($row[DepartmentImportColumnEnum::EMAIL->value] ?? ''));
        if ($email === '') {
            return null;
        }
        $errorMessage = Email::validate($email);
        if (null !== $errorMessage) {
            return $this->messageService->get($errorMessage, [], 'validators');
        }

        $internalCode = (string) ($row[DepartmentImportColumnEnum::DEPARTMENT_INTERNAL_CODE->value] ?? '');
        $emailsInternalCodes = $additionalData['emailsInternalCodes'] ?? [];
        if (array_key_exists($email, $emailsInternalCodes) && $emailsInternalCodes[$email] !== $internalCode) {
            return $this->messageService->get('department.email.alreadyExists', [':email' => $email], 'departments');
        }

        return null;
    }
}
