<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Trait\StringEnumTrait;

enum TimeStampableEntityFieldEnum: string
{
    use StringEnumTrait;

    case CREATED_AT = 'createdAt';
    case UPDATED_AT = 'updatedAt';
    case DELETED_AT = 'deletedAt';
}
