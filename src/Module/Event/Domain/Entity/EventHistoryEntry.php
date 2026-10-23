<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_history')]
#[ORM\UniqueConstraint(name: 'event_history_revision', columns: ['event_uuid', 'revision'])]
class EventHistoryEntry
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;
    #[ORM\Column(length: 36)]
    private string $actorUuid;
    #[ORM\Column(length: 80)]
    private string $action;
    #[ORM\Column(type: 'json')]
    private array $details;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'integer')]
    private int $revision;

    public function __construct(CateringEvent $event, string $actorUuid, string $action, array $details, int $revision = 1)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->event = $event;
        $this->actorUuid = $actorUuid;
        $this->action = $action;
        $this->details = $details;
        $this->revision = $revision;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'eventUuid' => $this->event->uuid(), 'actorUuid' => $this->actorUuid,
            'action' => $this->action, 'details' => $this->details, 'revision' => $this->revision,
            'createdAt' => $this->createdAt->format(DATE_ATOM)];
    }

}
