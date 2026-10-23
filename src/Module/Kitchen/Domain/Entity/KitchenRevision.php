<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'kitchen_revision')]
#[ORM\UniqueConstraint(name: 'kitchen_revision_number', columns: ['order_uuid', 'number'])]
class KitchenRevision
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\ManyToOne(targetEntity: KitchenOrder::class)]
    #[ORM\JoinColumn(name: 'order_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private KitchenOrder $order;
    #[ORM\Column(type: 'integer')] private int $number;
    #[ORM\Column(type: 'json')] private array $snapshot;
    #[ORM\Column(length: 36)] private string $actorUuid;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(KitchenOrder $order, int $number, array $snapshot, string $actorUuid)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->order = $order;
        $this->number = $number;
        $this->snapshot = $snapshot;
        $this->actorUuid = $actorUuid;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'orderUuid' => $this->order->uuid(), 'number' => $this->number, 'snapshot' => $this->snapshot, 'actorUuid' => $this->actorUuid, 'createdAt' => $this->createdAt->format(DATE_ATOM)];
    }
}
