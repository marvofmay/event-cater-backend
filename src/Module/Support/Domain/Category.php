<?php

declare(strict_types=1);

namespace App\Module\Support\Domain;

enum Category: string
{
    case SystemError = 'system_error';
    case Billing = 'billing';
    case Account = 'account';
    case Data = 'data';
    case Feature = 'feature';
    case Question = 'question';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SystemError => 'Błąd systemu',
            self::Billing => 'Opłaty',
            self::Account => 'Konto i dostęp',
            self::Data => 'Dane i import',
            self::Feature => 'Propozycja funkcji',
            self::Question => 'Pytanie',
            self::Other => 'Inne',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $category): array => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
