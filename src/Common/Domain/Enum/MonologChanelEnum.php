<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum MonologChanelEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case MAIN = 'main';
    case EVENT_LOG = 'eventLog';
    case EVENT_STORE = 'eventStore';
    case IMPORT = 'import';
    case LOCAL_CACHE = 'localCache';
}
