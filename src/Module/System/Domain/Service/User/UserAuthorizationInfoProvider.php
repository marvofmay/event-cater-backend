<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\User;

use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use App\Module\System\Domain\Interface\Module\ModuleReaderInterface;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionReaderInterface;
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
        $employee = $user->getEmployee();

        if ($user->isTenantAdministrator()) {
            return $this->moduleReaderRepository
                ->getModules()
                ->map(fn ($m) => $m->getName())
                ->toArray();
        }

        if (null === $employee) { return []; }

        return array_unique(
            $employee->getRole()
                ->getAccesses()
                ->map(fn ($a) => $a->getModule()->getName())
                ->toArray()
        );
    }

    public function getAccesses(UserInterface $user): array
    {
        $employee = $user->getEmployee();

        if ($user->isTenantAdministrator()) {
            return $this->accessReaderRepository
                ->getAccesses()
                ->map(fn ($a) => $a->getName())
                ->toArray();
        }

        if (null === $employee) { return []; }

        return $employee->getRole()
            ->getAccesses()
            ->map(fn ($a) => $a->getName())
            ->toArray();
    }

    public function getPermissions(UserInterface $user): array
    {
        $employee = $user->getEmployee();

        if ($user->isTenantAdministrator()) {
            return $this->accessReaderRepository
                ->getAccesses()
                ->map(fn ($a) => $a->getName() . '.*')
                ->toArray();
        }

        if (null === $employee) { return []; }

        return $this->roleAccessPermissionReaderRepository
            ->getRoleAccessAndPermission($employee->getRole())
            ->map(fn ($rap) => $rap->getAccess()->getName() . '.' . $rap->getPermission()->getName())
            ->toArray();
    }
}
