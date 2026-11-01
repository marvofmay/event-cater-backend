<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Service\User\LoginPhone;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.create.validator')]
#[AutoconfigureTag('app.employee.update.validator')]
final readonly class EmployeeLoginPhoneValidator implements ValidatorInterface
{
    public function __construct(private Connection $connection) {}
    public function supports(CommandInterface|QueryInterface $data): bool { return true; }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        try {
            $phone = LoginPhone::normalize($data->phones[0] ?? '');
        } catch (\InvalidArgumentException $error) {
            throw new \Exception($error->getMessage(), 422);
        }
        $owner = $this->connection->fetchAssociative('SELECT employee_uuid FROM user WHERE login_phone = ?', [$phone]);
        if ($owner && ($owner['employee_uuid'] === null || $owner['employee_uuid'] !== ($data->employeeUUID ?? null))) {
            throw new \Exception('Ten numer telefonu jest już loginem innego użytkownika w tej firmie.', 409);
        }
    }
}
