<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

final readonly class GetEventVenue
{
    public function __construct(public string $uuid)
    {
    }
}
