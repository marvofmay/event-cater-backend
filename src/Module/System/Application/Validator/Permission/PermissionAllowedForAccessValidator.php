<?php

declare(strict_types=1);

namespace App\Module\System\Application\Validator\Permission;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\Company\Domain\Service\Role\AssignPermissionsPayloadParser;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use App\Module\System\Domain\Interface\Permission\PermissionReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.role.assignPermissions.validator')]
final readonly class PermissionAllowedForAccessValidator implements ValidatorInterface
{
    public function __construct(
        private AccessReaderInterface $accessReaderRepository,
        private PermissionReaderInterface $permissionReaderRepository,
        private AssignPermissionsPayloadParser $assignPermissionsPayloadParser,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return true;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        $parsedPayload = $this->assignPermissionsPayloadParser->parse($data);
        if ([] === $parsedPayload) {
            return;
        }

        $accesses = $this->mapByUUID($this->accessReaderRepository->getAccessesByUUIDs(array_keys($parsedPayload)));
        $permissionUUIDs = array_unique(array_merge([], ...array_values($parsedPayload)));
        $permissions = [] === $permissionUUIDs
            ? []
            : $this->mapByUUID($this->permissionReaderRepository->getPermissionsByUUIDs($permissionUUIDs));

        $errors = [];
        foreach ($parsedPayload as $accessUUID => $accessPermissionUUIDs) {
            if ([] === $accessPermissionUUIDs) {
                continue;
            }

            $access = $accesses[$accessUUID] ?? null;
            if (!$access instanceof Access) {
                continue;
            }

            $allowedPermissionNames = $this->allowedPermissionNames($access);
            foreach ($accessPermissionUUIDs as $permissionUUID) {
                $permission = $permissions[$permissionUUID] ?? null;
                if (!$permission instanceof Permission) {
                    continue;
                }

                if (in_array($permission->getName(), $allowedPermissionNames, true)) {
                    continue;
                }

                $errors[] = $this->translator->trans(
                    'role.access.permission.notAllowed',
                    [
                        ':permission' => $permission->getName(),
                        ':access' => $access->getName(),
                    ],
                    'roles'
                );
            }
        }

        if ([] !== $errors) {
            throw new \Exception(implode(', ', $errors), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedPermissionNames(Access $access): array
    {
        $accessEnum = AccessEnum::tryFromModuleAndName(
            $access->getModule()->getName(),
            $access->getName()
        );

        if (null === $accessEnum) {
            return [];
        }

        return array_map(
            static fn ($permissionEnum) => $permissionEnum->value,
            $accessEnum->allowedPermissions()
        );
    }

    /**
     * @return array<string, Access|Permission>
     */
    private function mapByUUID(iterable $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getUUID()->toString()] = $entity;
        }

        return $map;
    }
}
