<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\User;

final class PasswordPolicy
{
    public static function errors(#[\SensitiveParameter] string $password, #[\SensitiveParameter] string $confirmation): array
    {
        $errors = [];
        if (mb_strlen($password) < 8 || mb_strlen($password) > 64 || strlen($password) > 72) {
            $errors[] = 'Hasło musi mieć od 8 do 64 znaków i nie więcej niż 72 bajty.';
        }
        foreach (['/\p{Lu}/u' => 'dużą literę', '/\p{Ll}/u' => 'małą literę', '/[0-9]/' => 'cyfrę', '/[^\p{L}\p{N}\s]/u' => 'znak specjalny'] as $pattern => $label) {
            if (!preg_match($pattern, $password)) {
                $errors[] = 'Hasło musi zawierać '.$label.'.';
            }
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
            $errors[] = 'Hasło nie może zawierać znaków sterujących.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'Podane hasła muszą być identyczne.';
        }

        return $errors;
    }
}
