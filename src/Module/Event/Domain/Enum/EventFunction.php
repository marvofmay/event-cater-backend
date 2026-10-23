<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Enum;

enum EventFunction: string
{
    case DRIVER = 'driver';
    case WAITER = 'waiter';
    case LOGISTICS = 'logistics';
    case CHEF = 'chef';
    case COORDINATOR = 'coordinator';
}
