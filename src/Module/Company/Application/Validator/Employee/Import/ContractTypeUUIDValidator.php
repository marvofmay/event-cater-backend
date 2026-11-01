<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\Employee\Import;

use App\Common\Domain\Interface\ImportRowValidatorInterface;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.employee.import.validator')]
final readonly class ContractTypeUUIDValidator implements ImportRowValidatorInterface
{
    public function __construct(private MessageService $messageService)
    {
    }

    public function validate(array $row, array $additionalData = []): ?string
    {
        $contractTypeUUID = $row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value] ?? null;
        if (empty($contractTypeUUID)) {
            return null;
        }

        $contractTypeExists = isset($additionalData['contractTypes'][$contractTypeUUID]);
        if (!$contractTypeExists) {
            return $this->messageService->get('contractType.uuid.notExists', [':uuid' => $contractTypeUUID], 'contract_types');
        }

        return null;
    }
}
