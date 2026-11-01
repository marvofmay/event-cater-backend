<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Shared\Utils\EmailValidator as Email;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class EmailValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService, private Connection $connection)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $email = trim((string) ($row[EmployeeImportColumnEnum::EMAIL->value] ?? ''));
        if ('' === $email) {
            return 'Podaj adres e-mail pracownika.';
        }
        if (mb_strlen($email) > 100) {
            return 'Adres e-mail może mieć maksymalnie 100 znaków.';
        }

        $errorMessage = Email::validate($email);
        if (null !== $errorMessage) {
            return $this->messageService->get($errorMessage, [], 'validators');
        }

        $pesel = (string) $row[EmployeeImportColumnEnum::PESEL->value] ?? null;
        if (isset($additionalData['duplicateLoginEmails'][strtolower($email)])) {
            return 'Ten sam e-mail został podany dla różnych pracowników w pliku.';
        }
        $owner = $this->connection->fetchAssociative('SELECT e.pesel FROM user u LEFT JOIN employee e ON e.uuid = u.employee_uuid WHERE u.email = ?', [$email]);
        if ($owner && $owner['pesel'] !== $pesel) {
            return 'Ten e-mail należy już do innego konta w tej firmie.';
        }
        if (array_key_exists($email, ($additionalData['emailsPESELs'] ?? [])) && $additionalData['emailsPESELs'][$email] !== $pesel) {
            return $this->messageService->get('employee.email.alreadyExists', [':email' => $email], 'employees');
        }

        return null;
    }
}
