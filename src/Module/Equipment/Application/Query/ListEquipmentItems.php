<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListEquipmentItems
{
    public function __construct(public Page $page, public string $search = '', public ?bool $active = true, public ?string $category = null, public ?string $storageLocationUuid = null)
    {
    }
}
