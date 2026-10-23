<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Command;

final readonly class CreateConsumable
{
    public function __construct(public string $uuid, public string $code, public string $name, public string $category, public string $unit, public ?string $description)
    {
    }
}
