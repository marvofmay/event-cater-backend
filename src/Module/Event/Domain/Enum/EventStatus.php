<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Enum;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PLANNED = 'planned';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::DRAFT => in_array($next, [self::PLANNED, self::CANCELLED], true),
            self::PLANNED => in_array($next, [self::DRAFT, self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($next, [self::IN_PROGRESS, self::CANCELLED], true),
            self::IN_PROGRESS => in_array($next, [self::COMPLETED, self::CANCELLED], true),
            self::COMPLETED, self::CANCELLED => false,
        };
    }
}
