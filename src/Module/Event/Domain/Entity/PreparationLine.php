<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\ValueObject\Quantity;
use App\Module\Event\Domain\ValueObject\PreparationRule;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_preparation_line')]
#[ORM\UniqueConstraint(name: 'preparation_consumable_unique', columns: ['preparation_uuid', 'consumable_uuid'])]
class PreparationLine
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: EventPreparationList::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'preparation_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private EventPreparationList $list;
    #[ORM\OneToOne(targetEntity: EquipmentRequirement::class)]
    #[ORM\JoinColumn(name: 'equipment_requirement_uuid', referencedColumnName: 'uuid', nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentRequirement $equipment = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $consumableUuid = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $requiredQuantity = null;
    #[ORM\Column(length: 200)]
    private string $name;
    #[ORM\Column(length: 50)]
    private string $code;
    #[ORM\Column(length: 10)]
    private string $unit;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $preparedQuantity = '0.000';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $loadedQuantity = '0.000';
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $responsibleUuid = null;
    #[ORM\Column(length: 250, nullable: true)]
    private ?string $responsibleName = null;
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $sourceLocation = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $preparedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $preparedAt = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $loadedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $loadedAt = null;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rule = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $sourceTemplateUuid = null;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sourceTemplateVersion = null;

    private function __construct(EventPreparationList $list, string $name, string $code, string $unit)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->list = $list;
        $this->name = $name;
        $this->code = $code;
        $this->unit = $unit;
    }

    public static function equipment(EventPreparationList $list, EquipmentRequirement $equipment): self
    {
        if ($equipment->eventUuid() !== $list->eventUuid()) {
            throw new BusinessRuleViolation('Equipment belongs to another event.');
        }
        $snapshot = $equipment->toArray();
        $self = new self($list, $snapshot['name'], $snapshot['code'], $snapshot['unit']);
        $self->equipment = $equipment;
        return $self;
    }

    public static function consumable(EventPreparationList $list, array $snapshot, Quantity $quantity): self
    {
        $self = new self($list, $snapshot['name'], $snapshot['code'], $snapshot['unit']);
        $self->consumableUuid = Uuid::fromString($snapshot['uuid'])->toString();
        $self->setRequiredQuantity($quantity);
        return $self;
    }

    public function setRequiredQuantity(Quantity $quantity): void
    {
        if ($this->equipment !== null) {
            throw new BusinessRuleViolation('Equipment demand is edited through event equipment.');
        }
        $quantity->assertUnit($this->unit);
        if ($quantity->isZero()) {
            throw new BusinessRuleViolation('Required quantity must be positive.');
        }
        $this->requiredQuantity = $quantity->decimal();
    }

    public function configure(?string $responsibleUuid, ?string $responsibleName, ?string $sourceLocation, ?string $instructions): void
    {
        if (($sourceLocation !== null && mb_strlen($sourceLocation) > 200) || ($instructions !== null && mb_strlen($instructions) > 2000)) {
            throw new BusinessRuleViolation('Source location maximum 200 characters; instructions maximum 2000.');
        }
        $this->responsibleUuid = $responsibleUuid;
        $this->responsibleName = $responsibleName;
        $this->sourceLocation = $sourceLocation;
        $this->instructions = $instructions;
    }

    public function useRule(?PreparationRule $rule, ?string $templateUuid = null, ?int $templateVersion = null): void
    {
        $this->rule = $rule?->toArray();
        if ($templateUuid !== null) {
            $this->sourceTemplateUuid = $templateUuid;
            $this->sourceTemplateVersion = $templateVersion;
        }
    }

    public function recordProgress(?Quantity $prepared, ?Quantity $loaded, string $actorUuid, ?string $correctionReason, bool $approved): bool
    {
        $oldPrepared = $this->prepared();
        $oldLoaded = $this->loaded();
        $prepared ??= $oldPrepared;
        $loaded ??= $oldLoaded;
        $prepared->assertUnit($this->unit);
        $loaded->assertUnit($this->unit);
        if ($prepared->lessThan($loaded)) {
            throw new BusinessRuleViolation('Loaded quantity cannot exceed prepared quantity.');
        }
        if (!$approved && ($oldPrepared->lessThan($prepared) || $oldLoaded->lessThan($loaded))) {
            throw new BusinessRuleViolation('Approve the current plan before preparing or loading more items.');
        }
        if (($prepared->lessThan($oldPrepared) || $loaded->lessThan($oldLoaded)) && mb_strlen(trim($correctionReason ?? '')) < 10) {
            throw new BusinessRuleViolation('Reducing physical quantities requires a correctionReason of at least 10 characters.');
        }
        if ($correctionReason !== null && mb_strlen($correctionReason) > 2000) {
            throw new BusinessRuleViolation('Correction reason exceeds 2000 characters.');
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $changed = false;
        if ($prepared->decimal() !== $this->preparedQuantity) {
            $this->preparedQuantity = $prepared->decimal();
            $this->preparedBy = $actorUuid;
            $this->preparedAt = $now;
            $changed = true;
        }
        if ($loaded->decimal() !== $this->loadedQuantity) {
            $this->loadedQuantity = $loaded->decimal();
            $this->loadedBy = $actorUuid;
            $this->loadedAt = $now;
            $changed = true;
        }
        return $changed;
    }

    public function assertRemovable(): void
    {
        if (!$this->prepared()->isZero() || !$this->loaded()->isZero()) {
            throw new BusinessRuleViolation('Correct physical quantities to zero before removing this line.');
        }
    }

    public function eventUuid(): string
    {
        return $this->list->eventUuid();
    }
    public function uuid(): string
    {
        return $this->uuid;
    }
    public function kind(): string
    {
        return $this->equipment !== null ? 'equipment' : 'consumable';
    }
    public function itemUuid(): string
    {
        return $this->equipment?->equipmentUuid() ?? $this->consumableUuid;
    }
    public function equipmentRequirement(): ?EquipmentRequirement
    {
        return $this->equipment;
    }
    public function required(): Quantity
    {
        return $this->equipment !== null ? Quantity::from($this->equipment->quantity()) : Quantity::from($this->requiredQuantity);
    }
    public function prepared(): Quantity
    {
        return Quantity::from($this->preparedQuantity);
    }
    public function loaded(): Quantity
    {
        return Quantity::from($this->loadedQuantity);
    }
    public function unit(): string
    {
        return $this->unit;
    }
    public function responsibleUuid(): ?string
    {
        return $this->responsibleUuid;
    }
    public function calculationRule(): ?PreparationRule
    {
        return $this->rule === null ? null : PreparationRule::restore($this->rule);
    }
    public function missingToPrepare(): Quantity
    {
        return $this->required()->missingFrom($this->prepared());
    }
    public function missingToLoad(): Quantity
    {
        return $this->required()->missingFrom($this->loaded());
    }

    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'kind' => $this->kind(), 'itemUuid' => $this->itemUuid(), 'name' => $this->name, 'code' => $this->code, 'unit' => $this->unit,
            'requiredQuantity' => $this->required()->decimal(), 'preparedQuantity' => $this->preparedQuantity, 'loadedQuantity' => $this->loadedQuantity,
            'missingToPrepare' => $this->missingToPrepare()->decimal(), 'missingToLoad' => $this->missingToLoad()->decimal(),
            'extraPrepared' => $this->prepared()->excessOver($this->required())->decimal(), 'extraLoaded' => $this->loaded()->excessOver($this->required())->decimal(),
            'planningNotes' => $this->equipment?->toArray()['notes'], 'responsibleUuid' => $this->responsibleUuid, 'responsibleName' => $this->responsibleName,
            'sourceLocation' => $this->sourceLocation, 'instructions' => $this->instructions,
            'preparedBy' => $this->preparedBy, 'preparedAt' => $this->preparedAt?->format(DATE_ATOM), 'loadedBy' => $this->loadedBy, 'loadedAt' => $this->loadedAt?->format(DATE_ATOM),
            'calculationRule' => $this->rule, 'sourceTemplateUuid' => $this->sourceTemplateUuid, 'sourceTemplateVersion' => $this->sourceTemplateVersion];
    }
}
