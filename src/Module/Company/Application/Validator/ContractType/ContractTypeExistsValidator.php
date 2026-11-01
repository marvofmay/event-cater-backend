<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Validator\ContractType;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Interface\ContractType\ContractTypeReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.contract_type.update.validator')]
#[AutoconfigureTag('app.contract_type.delete.validator')]
#[AutoconfigureTag('app.contract_type.query.get.validator')]
#[AutoconfigureTag('app.employee.create.validator')]
#[AutoconfigureTag('app.employee.update.validator')]
final readonly class ContractTypeExistsValidator implements ValidatorInterface
{
    public function __construct(private ContractTypeReaderInterface $contractTypeReaderRepository, private TranslatorInterface $translator)
    {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return true;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        if (!property_exists($data, 'contractTypeUUID')) {
            return;
        }

        $contractTypeUUID = $data->contractTypeUUID;
        if (null === $contractTypeUUID || '' === $contractTypeUUID) {
            return;
        }

        $contractTypeExists = $this->contractTypeReaderRepository->isContractTypeWithUUIDExists($contractTypeUUID);
        if (!$contractTypeExists) {
            throw new \Exception($this->translator->trans('contractType.uuid.notExists', [':uuid' => $contractTypeUUID], 'contract_types'), Response::HTTP_CONFLICT);
        }
    }
}
