<?php

declare(strict_types=1);

namespace App\Common\Domain\Trait;

trait StringEnumTrait
{
    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
