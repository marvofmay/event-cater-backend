<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\User;

final class LoginPhone
{
    public static function normalize(string $phone): string
    {
        $phone = trim($phone);
        if (!preg_match('/^\+?[0-9 ()\.\-]+$/D', $phone)) {
            throw new \InvalidArgumentException('Podaj prawidłowy numer telefonu.');
        }
        $international = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (!$international && strlen($digits) === 9) {
            $digits = '48'.$digits;
        }
        if (!preg_match('/^[1-9][0-9]{8,14}$/D', $digits)) {
            throw new \InvalidArgumentException('Podaj numer telefonu z kodem kraju lub 9-cyfrowy numer polski.');
        }

        return $digits;
    }
}
