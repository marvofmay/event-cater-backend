<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Role;

use App\Common\Domain\Enum\DeleteTypeEnum;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Interface\Role\RoleAccessPermissionDeleterInterface;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionWriterInterface;

final readonly class RoleAccessPermissionDeleter implements RoleAccessPermissionDeleterInterface
{
    public function __construct(private RoleAccessPermissionWriterInterface $roleAccessPermissionWriterRepository)
    {
    }

    public function delete(Role $role, Access $access): void
    {
        foreach ($role->getAccessPermissions()->toArray() as $relation) {
            if ($relation->getAccess()->getUUID()->toString() === $access->getUUID()->toString()) {
                $role->removeAccessPermission($access, $relation->getPermission());
            }
        }

        $this->roleAccessPermissionWriterRepository->deleteRoleAccessPermissionsByRoleAndAccessInDB(
            role: $role,
            access: $access,
            deleteTypeEnum: DeleteTypeEnum::HARD_DELETE
        );
    }
}
