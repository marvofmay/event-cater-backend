<?php

declare(strict_types=1);

namespace App\Module\System\Application\Validator\Permission;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Service\Role\AssignPermissionsPayloadParser;
use App\Module\System\Domain\Interface\Permission\PermissionReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.role.assignPermissions.validator')]
final readonly class PermissionsExistsValidator implements ValidatorInterface
{
    public function __construct(
        private PermissionReaderInterface $permissionReaderRepository,
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
        $permissionsUUIDs = array_merge(...array_values($parsedPayload));
        $permissionsUUIDs = array_unique($permissionsUUIDs);

        if (empty($permissionsUUIDs)) {
            return;
        }

        $foundRAccesses = $this->permissionReaderRepository
            ->getPermissionsByUUIDs($permissionsUUIDs)
            ->filter(fn ($permission) => $permission->getActive())
            ->map(fn ($access) => $access->getUUID())
            ->toArray();

        $missing = array_diff($permissionsUUIDs, $foundRAccesses);

        if (!empty($missing)) {
            $translatedErrors = array_map(
                fn (string $uuid) => $this->translator->trans('permission.uuid.notExists', [':uuid' => $uuid], 'permissions'),
                $missing
            );

            throw new \Exception(implode(', ', $translatedErrors), Response::HTTP_NOT_FOUND);
        }
    }
}
