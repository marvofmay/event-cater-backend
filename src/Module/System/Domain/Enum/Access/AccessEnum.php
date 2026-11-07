<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Enum\Access;

use App\Common\Domain\Interface\EnumInterface;
use App\Module\System\Domain\Enum\ModuleEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;

enum AccessEnum: string implements EnumInterface
{
    case EVENT_VENUES = ModuleEnum::EVENTS->value . '.venues';
    case KITCHEN_ORDERS = ModuleEnum::KITCHEN->value . '.orders';
    case CONSUMABLES = ModuleEnum::SUPPLIES->value . '.consumables';
    case PREPARATION_TEMPLATES = ModuleEnum::EVENTS->value . '.preparation_templates';
    case EVENTS = ModuleEnum::EVENTS->value . '.events';
    case EQUIPMENT = ModuleEnum::EQUIPMENT->value . '.equipment';
    case MANAGE = ModuleEnum::COMPANY->value . '.manage';
    case COMPANY_STRUCTURE = ModuleEnum::COMPANY->value . '.company_structure';
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
    case DOCUMENTS = ModuleEnum::DOCUMENTS->value . '.documents';
    case FILE_MANAGER = ModuleEnum::DOCUMENTS->value . '.file_manager';
    case MY_HOURS = ModuleEnum::MY_HOURS->value . '.my_hours';
    case EMPLOYEE_HOURS = ModuleEnum::EMPLOYEE_HOURS->value . '.employee_hours';
    case COMPANY_NOTICES = ModuleEnum::COMPANY_NOTICES->value . '.company_notices';

    /**
     * @return PermissionEnum[]
     */
    public function allowedPermissions(): array
    {
        return match ($this) {
            self::MESSAGES => [PermissionEnum::LIST, PermissionEnum::VIEW, PermissionEnum::CREATE, PermissionEnum::DELETE],
            self::MY_HOURS => [PermissionEnum::LIST, PermissionEnum::VIEW, PermissionEnum::CREATE, PermissionEnum::UPDATE, PermissionEnum::DELETE],
            self::EMPLOYEE_HOURS => [PermissionEnum::LIST, PermissionEnum::VIEW],
            self::COMPANY_NOTICES => [PermissionEnum::LIST, PermissionEnum::VIEW, PermissionEnum::CREATE, PermissionEnum::UPDATE, PermissionEnum::DELETE],
            self::COMPANY_STRUCTURE => [PermissionEnum::VIEW],
            self::EVENT_VENUES,
            self::CONSUMABLES,
            self::PREPARATION_TEMPLATES,
            self::EQUIPMENT => [
                PermissionEnum::CREATE,
                PermissionEnum::UPDATE,
                PermissionEnum::DELETE,
                PermissionEnum::RESTORE,
                PermissionEnum::VIEW,
                PermissionEnum::LIST,
            ],
            self::EVENTS => [
                PermissionEnum::CREATE,
                PermissionEnum::UPDATE,
                PermissionEnum::DELETE,
                PermissionEnum::VIEW,
                PermissionEnum::LIST,
                PermissionEnum::CONFIRM_EVENT,
                PermissionEnum::ASSIGN_EVENT_STAFF,
                PermissionEnum::PLAN_EVENT_MENU,
                PermissionEnum::PLAN_EVENT_EQUIPMENT,
                PermissionEnum::VIEW_EVENT_MEDIA,
                PermissionEnum::ADD_EVENT_MEDIA,
                PermissionEnum::DELETE_EVENT_MEDIA,
                PermissionEnum::PUBLISH_FACEBOOK,
                PermissionEnum::PLAN_EVENT_PREPARATION,
                PermissionEnum::PREPARE_EVENT_CATERING,
                PermissionEnum::LOAD_EVENT_CATERING,
                PermissionEnum::DISPATCH_EVENT_CATERING,
                PermissionEnum::ACCEPT_PREPARATION_SHORTAGES,
            ],
            self::KITCHEN_ORDERS => [
                PermissionEnum::VIEW,
                PermissionEnum::LIST,
                PermissionEnum::PDF,
                PermissionEnum::PLAN_KITCHEN_PRODUCTION,
                PermissionEnum::RELEASE_KITCHEN_PRODUCTION,
                PermissionEnum::PREPARE_KITCHEN_MEALS,
                PermissionEnum::HAND_OVER_KITCHEN_MEALS,
                PermissionEnum::ACCEPT_KITCHEN_REVISION,
                PermissionEnum::CANCEL_KITCHEN_ORDER,
            ],
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
            self::DOCUMENTS => [
                PermissionEnum::LIST,
                PermissionEnum::VIEW,
                PermissionEnum::CREATE,
                PermissionEnum::UPDATE,
                PermissionEnum::DELETE,
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
            self::FILE_MANAGER => [
                PermissionEnum::LIST,
                PermissionEnum::VIEW,
                PermissionEnum::CREATE,
                PermissionEnum::UPDATE,
                PermissionEnum::DELETE,
            ],
            self::MANAGE,
            self::IMPORTS,
            self::SETTINGS,
            self::ACCESSES,
            self::PERMISSIONS => [],
        };
    }

    public static function tryFromModuleAndName(string $moduleName, string $accessName): ?self
    {
        return self::tryFrom($moduleName.'.'.$accessName);
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::COMPANIES, self::DEPARTMENTS, self::EMPLOYEES, self::INDUSTRIES, self::ROLES, self::POSITIONS, self::CONTRACT_TYPES, self::IMPORTS => self::MANAGE,
            default => null,
        };
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
            self::EVENT_VENUES => 'Event venues',
            self::KITCHEN_ORDERS => 'Kitchen production orders',
            self::CONSUMABLES => 'Consumables',
            self::PREPARATION_TEMPLATES => 'Preparation templates',
            self::EVENTS => 'Events',
            self::EQUIPMENT => 'Equipment',
            self::MANAGE => 'Manage',
            self::COMPANY_STRUCTURE => 'Company structure',
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
            self::DOCUMENTS => 'Documents',
            self::FILE_MANAGER => 'File Manager',
            self::MY_HOURS => 'My hours',
            self::EMPLOYEE_HOURS => 'Employee hours',
            self::COMPANY_NOTICES => 'Company notices',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
