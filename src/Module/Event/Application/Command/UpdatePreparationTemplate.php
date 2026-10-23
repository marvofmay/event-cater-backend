<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class UpdatePreparationTemplate
{
    public function __construct(public string $uuid, public int $version, public string $name, public ?string $description, public array $lines)
    {
    }
}
