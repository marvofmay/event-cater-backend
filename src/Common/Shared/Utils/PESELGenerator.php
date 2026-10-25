<?php

declare(strict_types=1);

namespace App\Common\Shared\Utils;

final readonly class PESELGenerator
{
    public function generate(): string
    {
        $prefix = sprintf('%02d%02d%02d%04d', random_int(0, 99), random_int(1, 12), random_int(1, 28), random_int(0, 9999));

        return $prefix.PESELValidator::calculateCheckDigit($prefix);
    }
}
