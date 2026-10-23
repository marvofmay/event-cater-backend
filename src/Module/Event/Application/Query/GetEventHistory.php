<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class GetEventHistory
{
    public function __construct(public string $uuid, public Page $page)
    {
    }
}
