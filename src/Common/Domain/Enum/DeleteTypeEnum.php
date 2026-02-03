<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum DeleteTypeEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case HARD_DELETE = 'hard';
    case SOFT_DELETE = 'soft';
}
