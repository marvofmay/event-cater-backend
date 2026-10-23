<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class CreatePreparationTemplate
{
    public function __construct(public string $uuid, public string $name, public ?string $description, public array $lines)
    {
    }
}
