<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Command;

final readonly class ChangeConsumableActive
{
    public function __construct(public string $uuid, public int $version, public bool $active)
    {
    }
}
