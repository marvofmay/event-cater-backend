<?php

declare(strict_types=1);

namespace App\Module\System\Application\QueryHandler\Permission;

use App\Module\System\Application\Query\Permission\ListPermissionsQuery;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Enum\Permission\PermissionEntityFieldEnum;
use App\Module\System\Domain\Interface\Permission\PermissionReaderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPermissionsQueryHandler
{
    public function __construct(private PermissionReaderInterface $permissionReaderRepository)
    {
    }

    public function __invoke(ListPermissionsQuery $query): array
    {
        return $this->permissionReaderRepository
            ->getPermissions()
            ->filter(fn (Permission $permission) => $permission->getActive())
            ->map(static fn (Permission $permission) => [
                PermissionEntityFieldEnum::UUID->value => $permission->getUUID()->toString(),
                PermissionEntityFieldEnum::NAME->value => $permission->getName(),
                PermissionEntityFieldEnum::DESCRIPTION->value => $permission->getDescription(),
            ])
            ->getValues();
    }
}
