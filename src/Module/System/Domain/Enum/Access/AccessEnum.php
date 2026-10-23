<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Enum\Access;

use App\Common\Domain\Interface\EnumInterface;
use App\Module\System\Domain\Enum\ModuleEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;

enum AccessEnum: string implements EnumInterface
{
    case MANAGE = ModuleEnum::COMPANY->value . '.manage';
    case COMPANIES = ModuleEnum::COMPANY->value . '.companies';
    case DEPARTMENTS = ModuleEnum::COMPANY->value . '.departments';
    case EMPLOYEES = ModuleEnum::COMPANY->value . '.employees';
    case INDUSTRIES = ModuleEnum::COMPANY->value . '.industries';
    case ROLES = ModuleEnum::COMPANY->value . '.roles';
    case POSITIONS = ModuleEnum::COMPANY->value . '.positions';
    case CONTRACT_TYPES = ModuleEnum::COMPANY->value . '.contract_types';
    case IMPORTS = ModuleEnum::COMPANY->value . '.imports';
    case SETTINGS = ModuleEnum::SYSTEM->value . '.settings';
    case MESSAGES = ModuleEnum::SYSTEM->value . '.messages';
    case NOTIFICATIONS = ModuleEnum::SYSTEM->value . '.notifications';
    case NOTIFICATION_CHANNELS = ModuleEnum::SYSTEM->value . '.notification_channels';
    case NOTIFICATION_EVENTS = ModuleEnum::SYSTEM->value . '.notification_events';
    case NOTIFICATION_TEMPLATES = ModuleEnum::SYSTEM->value . '.notification_templates';
    case NOTIFICATION_MESSAGES = ModuleEnum::SYSTEM->value . '.notification_messages';
    case ACCESSES = ModuleEnum::SYSTEM->value . '.accesses';
    case PERMISSIONS = ModuleEnum::SYSTEM->value . '.permissions';
    case NOTES = ModuleEnum::NOTES->value . '.notes';
    //case DOCUMENTS = ModuleEnum::DOCUMENTS->value . '.documents';
    case FILE_MANAGER = ModuleEnum::DOCUMENTS->value . '.file_manager';

    /**
     * @return PermissionEnum[]
     */
    public function allowedPermissions(): array
    {
        return match ($this) {
            self::COMPANIES,
            self::DEPARTMENTS,
            self::EMPLOYEES,
            self::INDUSTRIES,
            self::POSITIONS,
            self::CONTRACT_TYPES => self::catalogPermissions(),
            self::ROLES => [
                ...self::catalogPermissions(),
                PermissionEnum::PDF,
                PermissionEnum::ASSIGN_ACCESS_TO_ROLE,
                PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE,
            ],
            self::NOTES => [
                PermissionEnum::CREATE,
                PermissionEnum::UPDATE,
                PermissionEnum::DELETE,
                PermissionEnum::VIEW,
                PermissionEnum::LIST,
                PermissionEnum::PDF,
            ],
            self::NOTIFICATION_MESSAGES => [
                PermissionEnum::LIST,
                PermissionEnum::VIEW,
                PermissionEnum::DELETE,
            ],
            self::NOTIFICATIONS,
            self::NOTIFICATION_CHANNELS,
            self::NOTIFICATION_EVENTS,
            self::NOTIFICATION_TEMPLATES => [
                PermissionEnum::LIST,
                PermissionEnum::SETTINGS,
            ],
            self::MANAGE,
            self::IMPORTS,
            self::SETTINGS,
            self::MESSAGES,
            self::ACCESSES,
            self::PERMISSIONS,
            self::FILE_MANAGER => [],
        };
    }

    public static function tryFromModuleAndName(string $moduleName, string $accessName): ?self
    {
        return self::tryFrom($moduleName.'.'.$accessName);
    }

    /**
     * @return PermissionEnum[]
     */
    private static function catalogPermissions(): array
    {
        return [
            PermissionEnum::CREATE,
            PermissionEnum::UPDATE,
            PermissionEnum::DELETE,
            PermissionEnum::RESTORE,
            PermissionEnum::VIEW,
            PermissionEnum::LIST,
            PermissionEnum::IMPORT,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::MANAGE => 'Manage',
            self::COMPANIES => 'Companies',
            self::DEPARTMENTS => 'Departments',
            self::EMPLOYEES => 'Employees',
            self::INDUSTRIES => 'Industries',
            self::ROLES => 'Roles',
            self::POSITIONS => 'Positions',
            self::CONTRACT_TYPES => 'Contract Types',
            self::IMPORTS => 'Imports',
            self::SETTINGS => 'Settings',
            self::MESSAGES => 'Messages',
            self::NOTIFICATIONS => 'Notifications',
            self::NOTIFICATION_CHANNELS => 'Notification Channels',
            self::NOTIFICATION_EVENTS => 'Notification Events',
            self::NOTIFICATION_TEMPLATES => 'Notification Templates',
            self::NOTIFICATION_MESSAGES => 'Notification Messages',
            self::ACCESSES => 'Accesses',
            self::PERMISSIONS => 'Permissions',
            self::NOTES => 'Notes',
            self::FILE_MANAGER => 'File Manager',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
