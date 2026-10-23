<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class SearchPreparationOptions
{
    public function __construct(public string $kind, public string $search = '')
    {
    }
}
