<?php

declare(strict_types=1);

namespace App\Module\FileManager\Domain;

use App\Common\Domain\Exception\BusinessRuleViolation;

final class FileName
{
    public static function validate(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) throw new BusinessRuleViolation('Nazwa pliku musi być poprawnym tekstem UTF-8.');
        if (class_exists(\Normalizer::class)) {
            $name = \Normalizer::normalize($name, \Normalizer::FORM_C) ?: $name;
        }
        if ($name !== trim($name) || $name === '' || mb_strlen($name) > 150 || strlen($name) > 240 || preg_match('/[\x00-\x1F\x7F\/\\\\<>:"|?*]/u', $name) || str_starts_with($name, '.') || str_ends_with($name, '.')) {
            throw new BusinessRuleViolation('Nazwa musi mieć od 1 do 150 znaków, bez ukośników, znaków specjalnych i kropki na początku lub końcu.');
        }
        return $name;
    }

    public static function key(string $name): string
    {
        return hash('sha256', mb_strtolower(self::validate($name), 'UTF-8'));
    }
}
