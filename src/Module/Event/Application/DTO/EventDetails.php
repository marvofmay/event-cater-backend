<?php

declare(strict_types=1);

namespace App\Module\Event\Application\DTO;

use App\Module\Event\Domain\ValueObject\EventLocation;
use App\Module\Event\Domain\ValueObject\EventSchedule;

final readonly class EventDetails
{
    public function __construct(
        public string $title,
        public EventSchedule $schedule,
        public EventLocation $location,
        public ?string $description = null,
        public ?int $guestCount = null,
        public ?string $coordinatorUuid = null,
        public ?string $customerName = null,
    ) {
    }
}
