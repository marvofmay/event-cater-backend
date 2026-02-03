<?php

declare(strict_types=1);

namespace App\Common\Domain\Enum;

use App\Common\Domain\Interface\EnumInterface;
use App\Common\Domain\Trait\StringEnumTrait;

enum FileExtensionEnum: string implements EnumInterface
{
    use StringEnumTrait;

    case PDF = 'pdf';
    case CSV = 'csv';
    case PNG = 'png';
    case XLSX = 'xlsx';
    case DOC = 'doc';
    case JPEG = 'jpeg';
    case JPG = 'jpg';
    case WEBP = 'webp';
}
