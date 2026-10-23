<?php

namespace App\Module\Company\Domain\Service\Role;

use App\Common\Domain\Enum\DeleteTypeEnum;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Interface\Role\AccessSynchronizerInterface;
use App\Module\Company\Domain\Interface\Role\RoleAccessPermissionDeleterInterface;
use App\Module\System\Domain\Interface\RoleAccess\RoleAccessWriterInterface;

final readonly class AccessSynchronizer implements AccessSynchronizerInterface
{
    public function __construct(
        private RoleAccessWriterInterface $roleAccessWriterRepository,
        private RoleAccessPermissionDeleterInterface $roleAccessPermissionDeleter,
    ) {
    }

    public function syncAccesses(Role $role, array $accessUUIDs, array $existingAccesses): void
    {
        foreach ($role->getAccesses() as $currentAccess) {
            $uuid = $currentAccess->getUUID()->toString();
            if (in_array($uuid, $accessUUIDs, true)) {
                $accessUUIDs = array_values(array_filter(
                    $accessUUIDs,
                    fn (string $code) => $code !== $uuid
                ));
                continue;
            }

            $this->roleAccessPermissionDeleter->delete($role, $currentAccess);
            $role->removeAccess($currentAccess);

            $this->roleAccessWriterRepository->deleteRoleAccessInDB(
                role: $role,
                access: $currentAccess,
                deleteTypeEnum: DeleteTypeEnum::HARD_DELETE
            );
        }

        $accessesToAdd = array_intersect_key(
            $existingAccesses,
            array_flip($accessUUIDs)
        );

        foreach ($accessesToAdd as $access) {
            $role->addAccess($access);
        }
    }
}
