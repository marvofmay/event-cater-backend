<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.employee.create.validator')]
#[AutoconfigureTag('app.employee.update.validator')]
final readonly class EmployeeAlreadyExistsValidator implements ValidatorInterface
{
    public function __construct(private EmployeeReaderInterface $employeeReaderRepository, private TranslatorInterface $translator)
    {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return true;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        if (!property_exists($data, 'pesel') || !property_exists($data, 'email')) {
            return;
        }

        $employeeUUID = $data->employeeUUID ?? null;
        $pesel = $data->pesel;
        $email = $data->email ?? '';
        $employeeExists = $this->employeeReaderRepository->isEmployeeAlreadyExistsWithEmailOrPESEL($pesel, $email, $employeeUUID);
        if ($employeeExists || ('' !== $email && $this->employeeReaderRepository->isEmployeeWithEmailExists($email, $employeeUUID))) {
            throw new \Exception($this->translator->trans('employee.alreadyExistsWithEmailsOrPESEL', [':pesel' => $pesel, ':email' => $email], 'employees'), Response::HTTP_CONFLICT);
        }
    }
}
