<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Enum\Permission;

use App\Common\Domain\Interface\EnumInterface;

enum PermissionEnum: string implements EnumInterface
{
    case PLAN_KITCHEN_PRODUCTION = 'plan_kitchen_production';
    case RELEASE_KITCHEN_PRODUCTION = 'release_kitchen_production';
    case PREPARE_KITCHEN_MEALS = 'prepare_kitchen_meals';
    case HAND_OVER_KITCHEN_MEALS = 'hand_over_kitchen_meals';
    case ACCEPT_KITCHEN_REVISION = 'accept_kitchen_revision';
    case CANCEL_KITCHEN_ORDER = 'cancel_kitchen_order';
    case PLAN_EVENT_PREPARATION = 'plan_event_preparation';
    case PREPARE_EVENT_CATERING = 'prepare_event_catering';
    case LOAD_EVENT_CATERING = 'load_event_catering';
    case DISPATCH_EVENT_CATERING = 'dispatch_event_catering';
    case ACCEPT_PREPARATION_SHORTAGES = 'accept_preparation_shortages';
    case CONFIRM_EVENT = 'confirm_event';
    case ASSIGN_EVENT_STAFF = 'assign_event_staff';
    case PLAN_EVENT_MENU = 'plan_event_menu';
    case PLAN_EVENT_EQUIPMENT = 'plan_event_equipment';
    case MANAGE_EVENT_MEDIA = 'manage_event_media';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case VIEW = 'view';
    case LIST = 'list';
    case IMPORT = 'import';
    case PDF = 'pdf';
    case ASSIGN_PERMISSION_TO_ACCESS_ROLE = 'assign_permission_to_access_role';
    case ASSIGN_ACCESS_TO_ROLE = 'assign_access_to_role';
    case SETTINGS = 'settings';

    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
