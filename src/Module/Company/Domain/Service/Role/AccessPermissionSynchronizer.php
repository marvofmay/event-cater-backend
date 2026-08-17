<?php

namespace App\Module\Company\Domain\Service\Role;

use App\Common\Domain\Enum\DeleteTypeEnum;
use App\Module\Company\Domain\Entity\Role;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionWriterInterface;

final readonly class AccessPermissionSynchronizer
{
    public function __construct(private RoleAccessPermissionWriterInterface $roleAccessPermissionWriterRepository)
    {
    }

    public function syncAccessPermissions(Role $role, Access $access, array $payloadPermissionsUUIDs, array $existingPermissions): void
    {
        $remainingUUIDs = $payloadPermissionsUUIDs;
        $accessUUID = $access->getUUID()->toString();

        foreach ($role->getAccessPermissions() as $roleAccessPermission) {
            if ($roleAccessPermission->getAccess()->getUUID()->toString() !== $accessUUID) {
                continue;
            }

            $currentPermission = $roleAccessPermission->getPermission();
            $uuid = $currentPermission->getUUID()->toString();
            if (in_array($uuid, $remainingUUIDs, true)) {
                $remainingUUIDs = array_values(array_filter(
                    $remainingUUIDs,
                    fn (string $code) => $code !== $uuid
                ));
                continue;
            }

            $role->removeAccessPermission(access: $access, permission: $currentPermission);

            $this->roleAccessPermissionWriterRepository->deleteRoleAccessPermissionsInDB(
                role: $role,
                access: $access,
                permission: $currentPermission,
                deleteTypeEnum: DeleteTypeEnum::HARD_DELETE
            );
        }

        $permissionsToAdd = array_intersect_key(
            $existingPermissions,
            array_flip($remainingUUIDs)
        );

        foreach ($permissionsToAdd as $permission) {
            $role->addAccessPermission(access: $access, permission: $permission);
        }
    }
}
