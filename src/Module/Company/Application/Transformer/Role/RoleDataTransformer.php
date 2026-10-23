<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Transformer\Role;

use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Common\Domain\Interface\DataTransformerInterface;
use App\Module\Company\Application\QueryHandler\Role\ListRolesQueryHandler;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Enum\Role\RoleEntityFieldEnum;
use App\Module\Company\Domain\Enum\Role\RoleEntityRelationFieldEnum;
use App\Module\System\Domain\Entity\Access;
use Doctrine\Common\Collections\Collection;

class RoleDataTransformer implements DataTransformerInterface
{
    public static function supports(): string
    {
        return ListRolesQueryHandler::class;
    }

    public function transformToArray(Role $role, array $includes = []): array
    {
        $data = [
            RoleEntityFieldEnum::UUID->value => $role->getUUID()->toString(),
            RoleEntityFieldEnum::NAME->value => $role->getName(),
            RoleEntityFieldEnum::DESCRIPTION->value => $role->getDescription(),
            TimeStampableEntityFieldEnum::CREATED_AT->value => $role->getCreatedAt()->format('Y-m-d H:i:s'),
            TimeStampableEntityFieldEnum::UPDATED_AT->value => $role->getUpdatedAt()?->format('Y-m-d H:i:s'),
            TimeStampableEntityFieldEnum::DELETED_AT->value => $role->getDeletedAt()?->format('Y-m-d H:i:s'),
        ];

        foreach ($includes as $relation) {
            if (in_array($relation, Role::getRelations(), true)) {
                $data[$relation] = $this->transformRelation($role, $relation);
            }
        }

        return $data;
    }

    public function transformAssignedAccesses(Role $role): array
    {
        $permissionsByAccess = [];
        foreach ($role->getAccessPermissions() as $roleAccessPermission) {
            $accessUUID = $roleAccessPermission->getAccess()->getUUID()->toString();
            $permissionsByAccess[$accessUUID][] = [
                'uuid' => $roleAccessPermission->getPermission()->getUUID()->toString(),
                'name' => $roleAccessPermission->getPermission()->getName(),
            ];
        }

        return $role->getAccesses()->map(static function (Access $access) use ($permissionsByAccess) {
            $accessUUID = $access->getUUID()->toString();

            return [
                Access::COLUMN_UUID => $accessUUID,
                Access::COLUMN_NAME => $access->getName(),
                Access::COLUMN_DESCRIPTION => $access->getDescription(),
                Access::RELATION_MODULE => [
                    'uuid' => $access->getModule()->getUUID()->toString(),
                    'name' => $access->getModule()->getName(),
                ],
                'permissions' => $permissionsByAccess[$accessUUID] ?? [],
            ];
        })->getValues();
    }

    private function transformRelation(Role $role, string $relation): ?array
    {
        return match ($relation) {
            RoleEntityRelationFieldEnum::EMPLOYEES->value => $this->transformEmployees($role->getEmployees()),
            default => null,
        };
    }

    private function transformEmployees(?Collection $employees): ?array
    {
        if (null === $employees || $employees->isEmpty()) {
            return null;
        }

        return array_map(
            fn (Employee $employee) => [
                Employee::COLUMN_UUID => $employee->getUUID()->toString(),
                Employee::COLUMN_FIRST_NAME => $employee->getFirstName(),
                Employee::COLUMN_LAST_NAME => $employee->getLastName(),
            ],
            $employees->toArray()
        );
    }
}
