<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class SearchEventEmployees
{
    public function __construct(public string $search = '')
    {
    }
}
