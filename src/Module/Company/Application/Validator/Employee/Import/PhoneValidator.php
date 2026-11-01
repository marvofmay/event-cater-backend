<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\Company\Domain\Service\User\LoginPhone;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class PhoneValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService, private Connection $connection)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $phone = trim((string) ($row[EmployeeImportColumnEnum::PHONE->value] ?? ''));
        if ('' === $phone) {
            return $this->messageService->get('employee.phone.required', [], 'employees');
        }
        try {
            $phone = LoginPhone::normalize($phone);
        } catch (\InvalidArgumentException $error) {
            return $error->getMessage();
        }
        if (isset($additionalData['duplicateLoginPhones'][$phone])) {
            return 'Ten sam telefon logowania został podany dla różnych pracowników w pliku.';
        }
        $owner = $this->connection->fetchAssociative('SELECT e.pesel FROM user u LEFT JOIN employee e ON e.uuid = u.employee_uuid WHERE u.login_phone = ?', [$phone]);
        if ($owner && $owner['pesel'] !== (string) ($row[EmployeeImportColumnEnum::PESEL->value] ?? '')) {
            return 'Ten numer telefonu jest już loginem innego użytkownika w tej firmie.';
        }

        return null;
    }
}
