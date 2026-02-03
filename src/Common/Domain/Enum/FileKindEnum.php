<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum FileKindEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case USER_AVATAR_PROFILE = 'user_avatar_profile';
    case COMPANY_LOGO = 'company_logo';
    case EMPLOYEE_AGREEMENT = 'employee_agreement';
    case IMPORT_XLSX = 'import_xlsx';
    case EMAIL_ATTACHMENTS = 'email_attachments';
}
