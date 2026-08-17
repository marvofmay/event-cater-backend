<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum ModuleEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case SYSTEM = 'system';
    case COMPANY = 'company';
    case NOTES = 'notes';
    case DOCUMENTS = 'documents';
    case FILE_MANAGER = 'file_manager';
}
