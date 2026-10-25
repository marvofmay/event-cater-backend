<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Persistance\Repository\Doctrine\RoleAccessPermission\Reader;

use App\Module\Company\Domain\Entity\Role;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Entity\RoleAccessPermission;
use App\Module\System\Domain\Entity\RoleAccess;
use App\Module\System\Domain\Interface\RoleAccessPermission\RoleAccessPermissionReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

class RoleAccessPermissionReaderRepository extends ServiceEntityRepository implements RoleAccessPermissionReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleAccessPermission::class);
    }

    public function isRoleHasAccessAndPermission(Permission $permission, Access $access, Role $role): bool
    {
        if ($role->getDeletedAt() !== null) {
            return false;
        }

        return $this->createQueryBuilder('assignment')
            ->innerJoin(RoleAccess::class, 'roleAccess', 'ON', 'roleAccess.role = assignment.role AND roleAccess.access = assignment.access')
            ->where('assignment.permission = :permission')
            ->andWhere('assignment.access = :access')
            ->andWhere('assignment.role = :role')
            ->setParameter('permission', $permission)
            ->setParameter('access', $access)
            ->setParameter('role', $role)
            ->getQuery()->getOneOrNullResult() !== null;
    }

    public function getRoleAccessAndPermission(Role $role): Collection
    {
        return new ArrayCollection($this->findBy([RoleAccessPermission::RELATION_ROLE => $role]));
    }
}
