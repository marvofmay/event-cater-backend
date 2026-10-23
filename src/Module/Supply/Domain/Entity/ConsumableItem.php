<?php

declare(strict_types=1);

namespace App\Module\Supply\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'consumable_item')]
#[ORM\Index(name: 'consumable_search', columns: ['active', 'name'])]
class ConsumableItem
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\Column(length: 50, unique: true)]
    private string $code;
    #[ORM\Column(length: 200)]
    private string $name;
    #[ORM\Column(length: 100)]
    private string $category;
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

    public static function create(string $uuid, string $code, string $name, string $category, string $unit, ?string $description): self
    {
        $self = new self();
        $self->uuid = Uuid::fromString($uuid)->toString();
        $self->replaceDetails($code, $name, $category, $unit, $description);
        return $self;
    }

    public function replaceDetails(string $code, string $name, string $category, string $unit, ?string $description): void
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,49}$/D', $code) || trim($name) === '' || mb_strlen($name) > 200 || trim($category) === '' || mb_strlen($category) > 100) {
            throw new BusinessRuleViolation('Consumable requires a unique code (1..50 letters/digits/-/_), name (1..200) and category (1..100).');
        }
        if (!in_array($unit, ['piece', 'carton', 'pack', 'bottle', 'kg', 'l'], true) || ($description !== null && mb_strlen($description) > 10000)) {
            throw new BusinessRuleViolation('Invalid consumable unit or description.');
        }
        $this->code = $code;
        $this->name = trim($name);
        $this->category = trim($category);
        $this->unit = $unit;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function changeActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function version(): int
    {
        return $this->version;
    }
    public function unit(): string
    {
        return $this->unit;
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'code' => $this->code, 'name' => $this->name, 'category' => $this->category,
            'unit' => $this->unit, 'description' => $this->description, 'active' => $this->active,
            'version' => $this->version, 'updatedAt' => $this->updatedAt->format(DATE_ATOM)];
    }
}
