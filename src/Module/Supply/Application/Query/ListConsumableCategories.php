<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListConsumableCategories
{
    public function __construct(public Page $page, public string $search = '')
    {
    }
}
