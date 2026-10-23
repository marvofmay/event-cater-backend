<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\Enum\EventStatus;
use App\Module\Event\Domain\ValueObject\EventContact;
use App\Module\Event\Domain\ValueObject\EventLocation;
use App\Module\Event\Domain\ValueObject\EventSchedule;
use App\Module\Event\Domain\ValueObject\MenuItem;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catering_event')]
#[ORM\Index(name: 'event_calendar', columns: ['event_date', 'status'])]
class CateringEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $uuid;

    #[ORM\Column(length: 40, unique: true)]
    private string $number;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'event_date', length: 10)]
    private string $date;

    #[ORM\Column(length: 64)]
    private string $timezone;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readyAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: 'json')]
    private array $location = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $guestCount = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $coordinatorUuid = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(type: 'json')]
    private array $contacts = [];

    #[ORM\Column(type: 'json')]
    private array $menu = [];

    #[ORM\Column(length: 20, enumType: EventStatus::class)]
    private EventStatus $status = EventStatus::DRAFT;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(string $uuid, string $title, EventSchedule $schedule): self
    {
        $self = new self();
        $self->uuid = Uuid::fromString($uuid)->toString();
        // UUID makes the business identifier unique without a shared sequence.
        $self->number = 'EVT-'.strtoupper(str_replace('-', '', $uuid));
        $self->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $self->replaceDetails($title, null, $schedule, new EventLocation(), null, null, null);
        return $self;
    }

    public function replaceDetails(string $title, ?string $description, EventSchedule $schedule, EventLocation $location, ?int $guestCount, ?string $coordinatorUuid, ?string $customerName): void
    {
        $this->assertEditable();
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new BusinessRuleViolation('title is required and must not exceed 200 characters.');
        }
        if (($description !== null && mb_strlen($description) > 20000)
            || ($customerName !== null && mb_strlen($customerName) > 200)) {
            throw new BusinessRuleViolation('Event description or customer name is too long.');
        }
        if ($guestCount !== null && ($guestCount < 1 || $guestCount > 1000000)) {
            throw new BusinessRuleViolation('guestCount must be between 1 and 1000000.');
        }
        if ($coordinatorUuid !== null && !Uuid::isValid($coordinatorUuid)) {
            throw new BusinessRuleViolation('Invalid coordinator UUID.');
        }
        if (in_array($this->status, [EventStatus::CONFIRMED, EventStatus::IN_PROGRESS], true)) {
            $this->assertComplete($schedule, $location, $guestCount, $coordinatorUuid, $this->contacts);
        }
        $utc = new \DateTimeZone('UTC');
        $this->title = $title;
        $this->description = $description;
        $this->date = $schedule->date;
        $this->timezone = $schedule->timezone;
        $this->readyAt = $schedule->readyAt?->setTimezone($utc);
        $this->startsAt = $schedule->startsAt?->setTimezone($utc);
        $this->endsAt = $schedule->endsAt?->setTimezone($utc);
        $this->location = $location->toArray();
        $this->guestCount = $guestCount;
        $this->coordinatorUuid = $coordinatorUuid;
        $this->customerName = $customerName;
        $this->touch();
    }

    /** @param list<EventContact> $contacts */
    public function replaceContacts(array $contacts): void
    {
        $this->assertEditable();
        if (count($contacts) > 50 || (in_array($this->status, [EventStatus::CONFIRMED, EventStatus::IN_PROGRESS], true) && $contacts === [])) {
            throw new BusinessRuleViolation('Up to 50 contacts allowed; confirmed events require at least one.');
        }
        $this->contacts = array_map(static fn (EventContact $contact) => $contact->toArray(), $contacts);
        $this->touch();
    }

    /** @param list<MenuItem> $items */
    public function replaceMenu(array $items): void
    {
        $this->assertEditable();
        if (count($items) > 300) {
            throw new BusinessRuleViolation('Maximum 300 menu items.');
        }
        $this->menu = array_map(static fn (MenuItem $item) => $item->toArray(), $items);
        $this->touch();
    }

    public function transitionTo(EventStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new BusinessRuleViolation('Invalid event status transition.');
        }
        if ($next === EventStatus::CONFIRMED) {
            $this->assertComplete($this->schedule(), new EventLocation(...$this->location), $this->guestCount, $this->coordinatorUuid, $this->contacts);
        }
        $this->status = $next;
        $this->touch();
    }

    private function assertComplete(EventSchedule $schedule, EventLocation $location, ?int $guestCount, ?string $coordinatorUuid, array $contacts): void
    {
        if (!$schedule->isComplete() || !$location->isComplete() || $guestCount === null || $coordinatorUuid === null || $contacts === []) {
            throw new BusinessRuleViolation('Confirmation requires full schedule, address, guestCount, coordinator and a contact.');
        }
    }

    public function assertEditable(): void
    {
        if (in_array($this->status, [EventStatus::COMPLETED, EventStatus::CANCELLED], true)) {
            throw new BusinessRuleViolation('Completed and cancelled events are read-only.');
        }
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function uuid(): string
    {
        return $this->uuid;
    }
    public function version(): int
    {
        return $this->version;
    }
    public function status(): EventStatus
    {
        return $this->status;
    }
    public function coordinatorUuid(): ?string
    {
        return $this->coordinatorUuid;
    }
    public function schedule(): EventSchedule
    {
        return new EventSchedule($this->date, $this->timezone, $this->readyAt, $this->startsAt, $this->endsAt);
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid, 'number' => $this->number, 'title' => $this->title,
            'description' => $this->description, 'status' => $this->status->value,
            ...$this->schedule()->toArray(), 'location' => $this->location,
            'guestCount' => $this->guestCount, 'coordinatorUuid' => $this->coordinatorUuid,
            'customerName' => $this->customerName, 'contacts' => $this->contacts, 'menu' => $this->menu,
            'version' => $this->version, 'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
