<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Command;

final readonly class SaveConsumableCategory
{
    public function __construct(public string $uuid, public string $name, public ?int $version = null)
    {
    }
}
