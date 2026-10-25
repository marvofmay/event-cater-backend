<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_photo')]
class EquipmentPhoto
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: EquipmentItem::class)]
    #[ORM\JoinColumn(name: 'equipment_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private EquipmentItem $equipment;
    #[ORM\Column(length: 150)]
    private string $name;
    #[ORM\Column(length: 50)]
    private string $storedName;
    #[ORM\Column(length: 50)]
    private string $mimeType;
    #[ORM\Column(type: 'integer')]
    private int $size;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(EquipmentItem $equipment, string $name, string $storedName, string $mimeType, int $size)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->equipment = $equipment;
        $this->name = $name;
        $this->storedName = $storedName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
