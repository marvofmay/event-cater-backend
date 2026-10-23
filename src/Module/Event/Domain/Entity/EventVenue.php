<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\ValueObject\EventContact;
use App\Module\Event\Domain\ValueObject\EventLocation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_venue')]
#[ORM\Index(name: 'event_venue_search', columns: ['active', 'name'])]
class EventVenue
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\Column(length: 200)] private string $name;
    #[ORM\Column(type: 'json')] private array $location;
    #[ORM\Column(type: 'json')] private array $contacts;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes;
    #[ORM\Column(length: 10)] private string $serviceMode;
    #[ORM\Column(type: 'boolean')] private bool $active = true;
    #[ORM\Version] #[ORM\Column(type: 'integer')] private int $version = 1;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $updatedAt;
    public function __construct(string $uuid, string $name, EventLocation $location, array $contacts, ?string $notes, string $serviceMode)
    {
        $this->uuid = Uuid::fromString($uuid)->toString();
        $this->replace($name, $location, $contacts, $notes, $serviceMode);
    }
    public function replace(string $name, EventLocation $location, array $contacts, ?string $notes, string $serviceMode): void
    {
        if (trim($name) === '' || mb_strlen($name) > 200 || !$location->isComplete() || count($contacts) > 50 || ($notes !== null && mb_strlen($notes) > 4000) || !in_array($serviceMode, ['off_site', 'on_site'], true)) {
            throw new BusinessRuleViolation('Venue requires a name, street/city/postal code, up to 50 contacts, notes up to 4000 characters and serviceMode off_site/on_site.');
        }
        $this->name = trim($name);
        $this->location = $location->toArray();
        $this->contacts = array_map(static fn (EventContact $c) => $c->toArray(), $contacts);
        $this->notes = $notes;
        $this->serviceMode = $serviceMode;
        $this->touch();
    }
    public function changeActive(bool $active): void
    {
        $this->active = $active;
        $this->touch();
    }
    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function version(): int
    {
        return $this->version;
    }
    public function active(): bool
    {
        return $this->active;
    }
    public function location(): EventLocation
    {
        return new EventLocation(...$this->location);
    }
    public function contacts(): array
    {
        return array_map(static fn (array $c) => new EventContact(...$c), $this->contacts);
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'location' => $this->location, 'contacts' => $this->contacts, 'notes' => $this->notes, 'serviceMode' => $this->serviceMode, 'active' => $this->active, 'version' => $this->version, 'updatedAt' => $this->updatedAt->format(DATE_ATOM)];
    }
}
