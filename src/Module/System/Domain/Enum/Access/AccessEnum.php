<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Enum\Access;

use App\Common\Domain\Interface\EnumInterface;
use App\Module\System\Domain\Enum\ModuleEnum;

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
            self::NOTIFICATIONS => 'Notifications',
            self::ACCESSES => 'Accesses',
            self::PERMISSIONS => 'Permissions',
            self::NOTES => 'Notes',
            //self::DOCUMENTS => 'Documents',
            self::FILE_MANAGER => 'File Manager',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
