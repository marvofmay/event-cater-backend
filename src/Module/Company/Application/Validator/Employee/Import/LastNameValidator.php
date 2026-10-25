<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class LastNameValidator implements ImportRowValidatorInterface
{
    public const int MINIMUM_LENGTH = 3;

    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $lastName = trim((string) ($row[EmployeeImportColumnEnum::LAST_NAME->value] ?? ''));

        if (empty($lastName)) {
            return null;
        }

        if (mb_strlen($lastName) < self::MINIMUM_LENGTH) {
            return $this->messageService->get('employee.lastName.minimumLength', [':qty' => self::MINIMUM_LENGTH], 'employees');
        }

        if (mb_strlen($lastName) > 50) {
            return $this->messageService->get('employee.lastName.maximumLength', [':qty' => 50], 'employees');
        }

        return null;
    }
}
