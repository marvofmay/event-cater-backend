<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListPreparationTemplates
{
    public function __construct(public Page $page, public string $search = '', public ?bool $active = true)
    {
    }
}
