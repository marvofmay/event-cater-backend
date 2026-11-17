<?php

declare(strict_types=1);

namespace App\Module\Support\Domain;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Nowe',
            self::InProgress => 'W trakcie',
            self::Waiting => 'Oczekuje na użytkownika',
            self::Resolved => 'Rozwiązane',
            self::Closed => 'Zamknięte',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
