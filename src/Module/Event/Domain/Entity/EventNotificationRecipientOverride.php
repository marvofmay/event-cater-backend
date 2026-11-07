<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_notification_recipient_override')]
#[ORM\UniqueConstraint(name: 'event_notification_recipient_unique', columns: ['event_uuid', 'employee_uuid'])]
class EventNotificationRecipientOverride
{
    public const KIND_EXTRA = 'extra';
    public const KIND_EXCLUDED = 'excluded';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;

    #[ORM\Column(name: 'employee_uuid', length: 36)]
    private string $employeeUuid;

    #[ORM\Column(length: 20)]
    private string $kind;

    public function __construct(CateringEvent $event, string $employeeUuid, string $kind, ?string $uuid = null)
    {
        if (!in_array($kind, [self::KIND_EXTRA, self::KIND_EXCLUDED], true)) {
            throw new \InvalidArgumentException('Invalid notification recipient override kind.');
        }
        $this->uuid = $uuid ?? \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->event = $event;
        $this->employeeUuid = $employeeUuid;
        $this->kind = $kind;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function event(): CateringEvent
    {
        return $this->event;
    }

    public function employeeUuid(): string
    {
        return $this->employeeUuid;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function changeKind(string $kind): void
    {
        if (!in_array($kind, [self::KIND_EXTRA, self::KIND_EXCLUDED], true)) {
            throw new \InvalidArgumentException('Invalid notification recipient override kind.');
        }
        $this->kind = $kind;
    }
}
