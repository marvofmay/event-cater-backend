<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Role;

use App\Common\Domain\Enum\DeleteTypeEnum;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Interface\Role\RoleAccessDeleterInterface;
use App\Module\Company\Domain\Interface\Role\RoleAccessPermissionDeleterInterface;
use App\Module\System\Domain\Interface\RoleAccess\RoleAccessWriterInterface;

final readonly class RoleAccessDeleter implements RoleAccessDeleterInterface
{
    public function __construct(
        private RoleAccessWriterInterface $roleAccessWriterRepository,
        private RoleAccessPermissionDeleterInterface $roleAccessPermissionDeleter,
    ) {
    }

    public function delete(Role $role): void
    {
        foreach ($role->getAccesses()->toArray() as $access) {
            $this->roleAccessPermissionDeleter->delete($role, $access);
        }

        $role->removeAccesses();
        $this->roleAccessWriterRepository->deleteRoleAccessesByRoleInDB(role: $role, deleteTypeEnum: DeleteTypeEnum::HARD_DELETE);
    }
}
