<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\Enum;

enum KitchenStatus: string
{
    case DRAFT = 'draft';
    case RELEASED = 'released';
    case IN_PRODUCTION = 'in_production';
    case READY = 'ready';
    case HANDED_OVER = 'handed_over';
    case CANCELLED = 'cancelled';
}
