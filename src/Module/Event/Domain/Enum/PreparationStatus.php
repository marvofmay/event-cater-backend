<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Enum;

enum PreparationStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case IN_PREPARATION = 'in_preparation';
    case READY_FOR_LOADING = 'ready_for_loading';
    case LOADED = 'loaded';
}
