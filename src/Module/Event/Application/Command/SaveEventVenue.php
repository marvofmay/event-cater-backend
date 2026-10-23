<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class SaveEventVenue
{
    public function __construct(public string $uuid, public ?int $version, public string $name, public \App\Module\Event\Domain\ValueObject\EventLocation $location, public array $contacts, public ?string $notes, public string $serviceMode)
    {
    }
}
