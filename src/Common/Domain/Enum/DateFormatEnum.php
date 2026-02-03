<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum DateFormatEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case DD_MM_YYYY = 'dd-mm-yyyy';
    case YYYY_MM_DD = 'yyyy-mm-dd';
}
