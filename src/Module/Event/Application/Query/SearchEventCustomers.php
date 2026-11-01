<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class SearchEventCustomers
{
    public function __construct(public string $search, public \App\Common\Application\DTO\Page $page = new \App\Common\Application\DTO\Page(1, 20))
    {
    }
}
