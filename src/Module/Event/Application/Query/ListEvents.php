<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListEvents
{
    public function __construct(public Page $page, public ?string $from = null, public ?string $to = null, public ?string $status = null, public string $search = '')
    {
    }
}
