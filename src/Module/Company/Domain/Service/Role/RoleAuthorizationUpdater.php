<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Role;

use App\Common\Domain\Enum\DeleteTypeEnum;
use App\Module\Company\Application\Event\Role\RoleAssignedAccessesEvent;
use App\Module\Company\Application\Event\Role\RoleAssignedPermissionsEvent;
use App\Module\Company\Domain\Entity\Role;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Domain\Interface\RoleAccess\RoleAccessWriterInterface;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionWriterInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RoleAuthorizationUpdater
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuthorizationCheckerInterface $authorization,
        private EventDispatcherInterface $events,
        private RoleAccessWriterInterface $accessWriter,
        private RoleAccessPermissionWriterInterface $permissionWriter,
    ) {
    }

    public function update(string $uuid, mixed $assignments): void
    {
        if (!Uuid::isValid($uuid) || !is_array($assignments) || !array_is_list($assignments)) {
            throw new HttpException(422, 'Wymagana jest lista dostępów i ich uprawnień.');
        }
        $this->em->wrapInTransaction(function () use ($uuid, $assignments): void {
            $role = $this->em->find(Role::class, $uuid, LockMode::PESSIMISTIC_WRITE);
            if (!$role) {
                throw new NotFoundHttpException('Nie znaleziono roli.');
            }
            $desired = $this->validate($assignments);
            $currentAccesses = [];
            $currentPermissions = [];
            foreach ($role->getAccesses() as $access) {
                $currentAccesses[$access->getUUID()->toString()] = true;
            }
            foreach ($role->getAccessPermissions() as $relation) {
                $currentPermissions[$relation->getAccess()->getUUID()->toString()][$relation->getPermission()->getUUID()->toString()] = true;
            }
            if (array_diff_key($currentAccesses, $desired) || array_diff_key($desired, $currentAccesses)) {
                $this->require(PermissionEnum::ASSIGN_ACCESS_TO_ROLE);
            }
            foreach ($desired as $accessUuid => $selection) {
                $existing = $currentPermissions[$accessUuid] ?? [];
                if (array_diff_key($existing, $selection['permissions']) || array_diff_key($selection['permissions'], $existing)) {
                    $this->require(PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE);
                }
            }
            foreach ($role->getAccessPermissions()->toArray() as $relation) {
                $accessUuid = $relation->getAccess()->getUUID()->toString();
                $permissionUuid = $relation->getPermission()->getUUID()->toString();
                if (!isset($desired[$accessUuid]['permissions'][$permissionUuid])) {
                    $role->removeAccessPermission($relation->getAccess(), $relation->getPermission());
                    $this->permissionWriter->deleteRoleAccessPermissionsInDB($role, $relation->getAccess(), $relation->getPermission(), DeleteTypeEnum::HARD_DELETE);
                }
            }
            foreach ($role->getRoleAccesses()->toArray() as $relation) {
                if (!isset($desired[$relation->getAccess()->getUUID()->toString()])) {
                    $role->removeAccess($relation->getAccess());
                    $this->accessWriter->deleteRoleAccessInDB($role, $relation->getAccess(), DeleteTypeEnum::HARD_DELETE);
                }
            }
            foreach ($desired as $accessUuid => $selection) {
                $role->addAccess($selection['access']);
                foreach ($selection['permissions'] as $permissionUuid => $permission) {
                    if (!isset($currentPermissions[$accessUuid][$permissionUuid])) {
                        $role->addAccessPermission($selection['access'], $permission);
                    }
                }
            }
            $this->em->persist($role);
            $this->events->dispatch(new RoleAssignedAccessesEvent(['roleUUID' => $uuid, 'accessesUUIDs' => array_keys($desired)]));
            $this->events->dispatch(new RoleAssignedPermissionsEvent(['roleUUID' => $uuid, 'accesses' => $assignments]));
        });
    }

    private function require(PermissionEnum $permission): void
    {
        if (!$this->authorization->isGranted($permission, AccessEnum::ROLES)) {
            throw new AccessDeniedHttpException('Brak uprawnień do zmiany dostępów lub uprawnień roli.');
        }
    }

    private function validate(array $assignments): array
    {
        $result = [];
        foreach ($assignments as $selection) {
            if (!is_array($selection) || array_diff(array_keys($selection), ['accessUUID', 'permissionsUUIDs']) || !is_string($selection['accessUUID'] ?? null) || !Uuid::isValid($selection['accessUUID']) || !is_array($selection['permissionsUUIDs'] ?? null) || !array_is_list($selection['permissionsUUIDs'])) {
                throw new HttpException(422, 'Nieprawidłowy dostęp lub lista uprawnień.');
            }
            $accessUuid = strtolower($selection['accessUUID']);
            if (isset($result[$accessUuid])) {
                throw new HttpException(422, 'Ten sam dostęp występuje więcej niż raz.');
            }
            $access = $this->em->find(Access::class, $accessUuid);
            $accessEnum = $access ? AccessEnum::tryFromModuleAndName($access->getModule()->getName(), $access->getName()) : null;
            if (!$access || !$access->getActive() || !$access->getModule()->getActive() || !$accessEnum) {
                throw new HttpException(422, 'Dostęp nie istnieje lub jest nieaktywny.');
            }
            $permissions = [];
            foreach ($selection['permissionsUUIDs'] as $permissionUuid) {
                if (!is_string($permissionUuid) || !Uuid::isValid($permissionUuid)) {
                    throw new HttpException(422, 'Nieprawidłowy identyfikator uprawnienia.');
                }
                $permissionUuid = strtolower($permissionUuid);
                $permission = $this->em->find(Permission::class, $permissionUuid);
                $permissionEnum = $permission ? PermissionEnum::tryFrom($permission->getName()) : null;
                if (!$permission || !$permission->getActive() || !$permissionEnum || !in_array($permissionEnum, $accessEnum->allowedPermissions(), true)) {
                    throw new HttpException(422, 'Uprawnienie nie jest dostępne dla wybranego dostępu.');
                }
                $permissions[$permissionUuid] = $permission;
            }
            $result[$accessUuid] = ['access' => $access, 'permissions' => $permissions];
        }

        return $result;
    }
}
