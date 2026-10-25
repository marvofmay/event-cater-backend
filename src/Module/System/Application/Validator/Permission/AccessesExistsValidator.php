<?php

declare(strict_types=1);

namespace App\Module\System\Application\Validator\Permission;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Service\Role\AssignPermissionsPayloadParser;
use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.role.assignPermissions.validator')]
final readonly class AccessesExistsValidator implements ValidatorInterface
{
    public function __construct(
        private AccessReaderInterface $accessReaderRepository,
        private AssignPermissionsPayloadParser $assignPermissionsPayloadParser,
        private TranslatorInterface $translator
    ) {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return true;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        $parsedPayload = $this->assignPermissionsPayloadParser->parse($data);

        $accessesUUIDs = array_keys($parsedPayload);
        $accessesUUIDs = array_unique($accessesUUIDs);

        if (empty($accessesUUIDs)) {
            return;
        }

        $foundRAccesses = $this->accessReaderRepository
            ->getAccessesByUUIDs($accessesUUIDs)
            ->filter(fn ($access) => $access->getActive() && $access->getModule()->getActive())
            ->map(fn ($access) => $access->getUUID())
            ->toArray();

        $missing = array_diff($accessesUUIDs, $foundRAccesses);

        if (!empty($missing)) {
            $translatedErrors = array_map(
                fn (string $uuid) => $this->translator->trans('access.uuid.notExists', [':uuid' => $uuid], 'accesses'),
                $missing
            );

            throw new \Exception(implode(', ', $translatedErrors), Response::HTTP_NOT_FOUND);
        }
    }
}
