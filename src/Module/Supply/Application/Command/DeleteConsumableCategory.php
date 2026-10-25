<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Command;

final readonly class DeleteConsumableCategory
{
    public function __construct(public string $uuid, public int $version, public ?string $replacementCategoryUuid = null)
    {
    }
}
