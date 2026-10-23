<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_equipment_requirement')]
#[ORM\UniqueConstraint(name: 'event_equipment_unique', columns: ['event_uuid', 'equipment_uuid'])]
class EquipmentRequirement
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;
    #[ORM\Column(length: 36)]
    private string $equipmentUuid;
    #[ORM\Column(length: 200)]
    private string $name;
    #[ORM\Column(length: 50)]
    private string $code;
    #[ORM\Column(length: 10)]
    private string $unit;
    #[ORM\Column(type: 'integer')]
    private int $quantity;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    public function __construct(CateringEvent $event, string $equipmentUuid, string $name, string $code, string $unit, int $quantity, ?string $notes)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->event = $event;
        $this->equipmentUuid = Uuid::fromString($equipmentUuid)->toString();
        $this->name = $name;
        $this->code = $code;
        $this->unit = $unit;
        $this->changeQuantity($quantity, $notes);
    }

    public function changeQuantity(int $quantity, ?string $notes): void
    {
        $this->event->assertEditable();
        if ($quantity < 1 || $quantity > 1000000 || ($notes !== null && mb_strlen($notes) > 4000)) {
            throw new BusinessRuleViolation('Equipment quantity must be 1..1000000; notes maximum 4000 characters.');
        }
        $this->quantity = $quantity;
        $this->notes = $notes;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }
    public function eventUuid(): string
    {
        return $this->event->uuid();
    }
    public function equipmentUuid(): string
    {
        return $this->equipmentUuid;
    }
    public function quantity(): int
    {
        return $this->quantity;
    }

    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'equipmentUuid' => $this->equipmentUuid, 'name' => $this->name,
            'code' => $this->code, 'unit' => $this->unit, 'quantity' => $this->quantity, 'notes' => $this->notes];
    }
}
