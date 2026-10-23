<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListConsumables
{
    public function __construct(public Page $page, public string $search = '', public ?bool $active = true, public ?string $category = null)
    {
    }
}
