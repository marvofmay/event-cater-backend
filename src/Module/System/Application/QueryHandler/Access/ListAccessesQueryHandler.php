<?php

declare(strict_types=1);

namespace App\Module\System\Application\QueryHandler\Access;

use App\Module\System\Application\Query\Access\ListAccessesQuery;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEntityFieldEnum;
use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use App\Module\System\Domain\Interface\Permission\PermissionReaderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAccessesQueryHandler
{
    public function __construct(
        private AccessReaderInterface $accessReaderRepository,
        private PermissionReaderInterface $permissionReaderRepository,
    ) {
    }

    public function __invoke(ListAccessesQuery $query): array
    {
        $permissionsByName = [];
        foreach ($this->permissionReaderRepository->getPermissions() as $permission) {
            if (!$permission->getActive()) {
                continue;
            }

            $permissionsByName[$permission->getName()] = $permission;
        }

        return $this->accessReaderRepository
            ->getAccesses()
            ->filter(fn (Access $access) => $access->getActive())
            ->map(fn (Access $access) => [
                Access::COLUMN_UUID => $access->getUUID()->toString(),
                Access::COLUMN_NAME => $access->getName(),
                Access::COLUMN_DESCRIPTION => $access->getDescription(),
                Access::RELATION_MODULE => [
                    'uuid' => $access->getModule()->getUUID()->toString(),
                    'name' => $access->getModule()->getName(),
                ],
                'availablePermissions' => $this->mapAvailablePermissions($access, $permissionsByName),
            ])
            ->getValues();
    }

    /**
     * @param array<string, Permission> $permissionsByName
     *
     * @return list<array{uuid: string, name: string}>
     */
    private function mapAvailablePermissions(Access $access, array $permissionsByName): array
    {
        $accessEnum = AccessEnum::tryFromModuleAndName(
            $access->getModule()->getName(),
            $access->getName()
        );

        if (null === $accessEnum) {
            return [];
        }

        $availablePermissions = [];
        foreach ($accessEnum->allowedPermissions() as $permissionEnum) {
            $permission = $permissionsByName[$permissionEnum->value] ?? null;
            if (!$permission instanceof Permission) {
                continue;
            }

            $availablePermissions[] = [
                PermissionEntityFieldEnum::UUID->value => $permission->getUUID()->toString(),
                PermissionEntityFieldEnum::NAME->value => $permission->getName(),
            ];
        }

        return $availablePermissions;
    }
}
