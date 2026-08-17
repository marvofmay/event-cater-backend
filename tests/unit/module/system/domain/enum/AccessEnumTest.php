<?php

declare(strict_types=1);

namespace App\tests\unit\module\system\domain\enum;

use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use PHPUnit\Framework\TestCase;

final class AccessEnumTest extends TestCase
{
    public function testNotesAllowOnlyNoteActions(): void
    {
        $names = array_map(
            static fn (PermissionEnum $permission) => $permission->value,
            AccessEnum::NOTES->allowedPermissions()
        );

        $this->assertContains(PermissionEnum::CREATE->value, $names);
        $this->assertContains(PermissionEnum::PDF->value, $names);
        $this->assertNotContains(PermissionEnum::SETTINGS->value, $names);
        $this->assertNotContains(PermissionEnum::ASSIGN_ACCESS_TO_ROLE->value, $names);
        $this->assertNotContains(PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE->value, $names);
        $this->assertNotContains(PermissionEnum::RESTORE->value, $names);
        $this->assertNotContains(PermissionEnum::IMPORT->value, $names);
    }

    public function testRolesAllowAssignmentActions(): void
    {
        $names = array_map(
            static fn (PermissionEnum $permission) => $permission->value,
            AccessEnum::ROLES->allowedPermissions()
        );

        $this->assertContains(PermissionEnum::ASSIGN_ACCESS_TO_ROLE->value, $names);
        $this->assertContains(PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE->value, $names);
        $this->assertContains(PermissionEnum::IMPORT->value, $names);
    }

    public function testManageHasNoAssignableActions(): void
    {
        $this->assertSame([], AccessEnum::MANAGE->allowedPermissions());
    }
}
