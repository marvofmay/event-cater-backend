<?php

declare(strict_types=1);

namespace App\Common\Shared\Utils;

final readonly class PESELValidator
{
    public static function validate(string $pesel): ?string
    {
        if (11 !== strlen($pesel) || !ctype_digit($pesel)) {
            return 'pesel.invalidLength';
        }

        $checksum = self::calculateCheckDigit(substr($pesel, 0, 10));

        if ($checksum !== (int) $pesel[10]) {
            return 'pesel.invalid';
        }

        return null;
    }

    public static function calculateCheckDigit(string $prefix): int
    {
        if (!preg_match('/^\d{10}$/D', $prefix)) {
            throw new \InvalidArgumentException('PESEL prefix must contain 10 digits.');
        }

        return self::calculateChecksum($prefix, [1, 3, 7, 9, 1, 3, 7, 9, 1, 3]);
    }

    private static function calculateChecksum(string $number, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += $weight * (int) $number[$i];
        }

        $modulo = $sum % 10;

        return (10 - $modulo) % 10;
    }
}
