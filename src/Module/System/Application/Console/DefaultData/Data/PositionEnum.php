<?php

declare(strict_types=1);

namespace App\Module\System\Application\Console\DefaultData\Data;

use App\Common\Domain\Interface\EnumInterface;

enum PositionEnum: string implements EnumInterface
{
    case OWNER = 'owner';
    case CATERING_MANAGER = 'catering_manager';
    case SALES_SPECIALIST = 'sales_specialist';
    case HEAD_CHEF = 'head_chef';
    case COOK = 'cook';
    case KITCHEN_ASSISTANT = 'kitchen_assistant';
    case PASTRY_CHEF = 'pastry_chef';
    case EVENT_SERVICE_MANAGER = 'event_service_manager';
    case EVENT_COORDINATOR = 'event_coordinator';
    case WAITER = 'waiter';
    case BARTENDER = 'bartender';
    case BUFFET_ATTENDANT = 'buffet_attendant';
    case LOGISTICS_MANAGER = 'logistics_manager';
    case DRIVER = 'driver';
    case PACKING_LOADER = 'packing_loader';
    case SETUP_STAFF = 'setup_staff';
    case MEAL_PACKER = 'meal_packer';
    case DISHWASHER = 'dishwasher';

    public function label(): string
    {
        return $this->value;
    }

    public function department(): string
    {
        return match ($this) {
            self::OWNER, self::CATERING_MANAGER, self::SALES_SPECIALIST => 'Zarząd',
            self::HEAD_CHEF, self::COOK, self::KITCHEN_ASSISTANT, self::PASTRY_CHEF => 'Kuchnia',
            self::EVENT_SERVICE_MANAGER, self::EVENT_COORDINATOR, self::WAITER, self::BARTENDER, self::BUFFET_ATTENDANT => 'Obsługa',
            self::LOGISTICS_MANAGER, self::DRIVER, self::PACKING_LOADER, self::SETUP_STAFF => 'Logistyka',
            self::MEAL_PACKER => 'Pakowanie',
            self::DISHWASHER => 'Czystość i higiena',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
