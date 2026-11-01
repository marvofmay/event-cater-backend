<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Sms;

final class SmsSegments
{
    public static function count(string $text): array
    {
        $basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $extended = "\f^{}\\[~]|€";
        $units = 0;
        foreach (mb_str_split($text) as $character) {
            if (str_contains($basic, $character)) {
                ++$units;
            } elseif (str_contains($extended, $character)) {
                $units += 2;
            } else {
                $length = (int) (strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) / 2);

                return ['units' => max(1, $length <= 70 ? 1 : (int) ceil($length / 67)), 'unicode' => true];
            }
        }

        return ['units' => max(1, $units <= 160 ? 1 : (int) ceil($units / 153)), 'unicode' => false];
    }
}
