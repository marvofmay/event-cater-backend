<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class ListEventVenues
{
    public function __construct(public \App\Common\Application\DTO\Page $page, public string $search, public ?bool $active)
    {
    }
}
