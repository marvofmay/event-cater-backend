<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'kitchen_history')]
#[ORM\UniqueConstraint(name: 'kitchen_audit_version', columns: ['order_uuid', 'order_version'])]
class KitchenHistoryEntry
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\ManyToOne(targetEntity: KitchenOrder::class)]
    #[ORM\JoinColumn(name: 'order_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private KitchenOrder $order;
    #[ORM\Column(type: 'integer')] private int $orderVersion;
    #[ORM\Column(length: 80)] private string $action;
    #[ORM\Column(type: 'json')] private array $details;
    #[ORM\Column(length: 36)] private string $actorUuid;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(KitchenOrder $order, string $action, array $details, string $actorUuid)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->order = $order;
        $this->action = $action;
        $this->details = $details;
        $this->orderVersion = $action === 'created' ? 1 : $order->version() + 1;
        $this->actorUuid = $actorUuid;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'orderUuid' => $this->order->uuid(), 'orderVersion' => $this->orderVersion, 'action' => $this->action, 'details' => $this->details, 'actorUuid' => $this->actorUuid, 'createdAt' => $this->createdAt->format(DATE_ATOM)];
    }
}
