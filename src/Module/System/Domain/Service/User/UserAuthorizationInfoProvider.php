<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\User;

use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use App\Module\System\Domain\Interface\Module\ModuleReaderInterface;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionReaderInterface;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserAuthorizationInfoProvider
{
    public function __construct(
        private ModuleReaderInterface $moduleReaderRepository,
        private AccessReaderInterface $accessReaderRepository,
        private RoleAccessPermissionReaderInterface $roleAccessPermissionReaderRepository,
    ) {
    }

    public function getModules(UserInterface $user): array
    {
        if ($user->isTenantAdministrator()) {
            return $this->moduleReaderRepository
                ->getModules()
                ->filter(fn ($module) => $module->getActive())
                ->map(fn ($m) => $m->getName())
                ->getValues();
        }

        return array_values(array_unique(array_map(fn ($access) => $access->getModule()->getName(), $this->grantedAccesses($user))));
    }

    public function getAccesses(UserInterface $user): array
    {
        return array_values(array_unique(array_map(fn ($access) => $access->getName(), $this->grantedAccesses($user))));
    }

    public function getPermissions(UserInterface $user): array
    {
        $employee = $user->getEmployee();

        if ($user->isTenantAdministrator()) {
            return array_map(fn ($access) => $access->getName().'.*', $this->grantedAccesses($user));
        }

        if (null === $employee) { return []; }

        $accesses = [];
        foreach ($this->grantedAccesses($user) as $access) {
            $accesses[$access->getUUID()->toString()] = true;
        }

        return $this->roleAccessPermissionReaderRepository
            ->getRoleAccessAndPermission($employee->getRole())
            ->filter(function ($assignment) use ($accesses) {
                $access = $assignment->getAccess();
                $permission = $assignment->getPermission();
                $accessEnum = AccessEnum::tryFromModuleAndName($access->getModule()->getName(), $access->getName());

                return isset($accesses[$access->getUUID()->toString()]) && $permission->getActive() && in_array(PermissionEnum::tryFrom($permission->getName()), $accessEnum?->allowedPermissions() ?? [], true);
            })
            ->map(fn ($rap) => $rap->getAccess()->getName() . '.' . $rap->getPermission()->getName())
            ->getValues();
    }

    private function grantedAccesses(UserInterface $user): array
    {
        if ($user->isTenantAdministrator()) {
            $accesses = $this->accessReaderRepository->getAccesses();
        } else {
            $role = $user->getEmployee()?->getRole();
            if (!$role || $role->getDeletedAt() !== null) {
                return [];
            }
            $accesses = $role->getAccesses();
        }

        return $accesses->filter(fn ($access) => $access->getActive() && $access->getModule()->getActive())->getValues();
    }
}
