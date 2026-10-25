<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_item')]
#[ORM\Index(name: 'equipment_search', columns: ['active', 'name'])]
class EquipmentItem
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\Column(length: 50, unique: true)]
    private string $code;
    #[ORM\Column(length: 200)]
    private string $name;
    #[ORM\ManyToOne(targetEntity: EquipmentCategory::class)]
    #[ORM\JoinColumn(name: 'category_uuid', referencedColumnName: 'uuid', nullable: false)]
    private EquipmentCategory $category;
    #[ORM\ManyToOne(targetEntity: EquipmentLocation::class)]
    #[ORM\JoinColumn(name: 'storage_location_uuid', referencedColumnName: 'uuid', nullable: true, onDelete: 'SET NULL')]
    private ?EquipmentLocation $storageLocation = null;
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $slangName = null;
    #[ORM\Column(type: 'integer')]
    private int $stockQuantity = 0;
    #[ORM\Column(length: 10)]
    private string $unit;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;
    #[ORM\Column(type: 'boolean')]
    private bool $active = true;
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(string $uuid, string $code, string $name, EquipmentCategory $category, string $unit, ?string $description, ?string $slangName = null, int $stockQuantity = 0): self
    {
        $self = new self();
        $self->uuid = Uuid::fromString($uuid)->toString();
        $self->replaceDetails($code, $name, $category, $unit, $description, $slangName, $stockQuantity);
        return $self;
    }

    public function replaceDetails(string $code, string $name, EquipmentCategory $category, string $unit, ?string $description, ?string $slangName = null, ?int $stockQuantity = null): void
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,49}$/D', $code)) {
            throw new BusinessRuleViolation('Equipment code must contain 1..50 letters, digits, underscores or hyphens.');
        }
        if (trim($name) === '' || mb_strlen($name) > 200) {
            throw new BusinessRuleViolation('Equipment name is required (maximum 200 characters).');
        }
        if ($slangName !== null && mb_strlen($slangName) > 200) {
            throw new BusinessRuleViolation('Nazwa slangowa może mieć maksymalnie 200 znaków.');
        }
        if ($stockQuantity !== null && ($stockQuantity < 0 || $stockQuantity > 2147483647)) {
            throw new BusinessRuleViolation('Ilość sprzętu musi być nieujemną liczbą całkowitą do 2147483647.');
        }
        if (!in_array($unit, ['piece', 'set', 'kit'], true)) {
            throw new BusinessRuleViolation('unit must be piece, set or kit.');
        }
        if ($description !== null && mb_strlen($description) > 10000) {
            throw new BusinessRuleViolation('Equipment description is too long.');
        }
        $this->code = $code;
        $this->name = trim($name);
        $this->category = $category;
        $this->slangName = $slangName === null || trim($slangName) === '' ? null : trim($slangName);
        if ($stockQuantity !== null) {
            $this->stockQuantity = $stockQuantity;
        }
        $this->unit = $unit;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function changeActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function recordPhotoChange(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function changeStorageLocation(?EquipmentLocation $location): void
    {
        $this->storageLocation = $location;
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
    public function isActive(): bool
    {
        return $this->active;
    }
    public function unit(): string
    {
        return $this->unit;
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'code' => $this->code, 'name' => $this->name,
            'category' => $this->category->name(), 'categoryUuid' => $this->category->uuid(),
            'storageLocation' => $this->storageLocation?->name(), 'storageLocationUuid' => $this->storageLocation?->uuid(),
            'slangName' => $this->slangName, 'stockQuantity' => $this->stockQuantity,
            'unit' => $this->unit, 'description' => $this->description,
            'active' => $this->active, 'version' => $this->version, 'updatedAt' => $this->updatedAt->format(DATE_ATOM)];
    }
}
