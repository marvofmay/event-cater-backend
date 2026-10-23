<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\ValueObject\PreparationRule;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'preparation_template')]
class PreparationTemplate
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\Column(length: 200)]
    private string $name;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;
    #[ORM\Column(name: 'template_items', type: 'json')]
    private array $lines;
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

    public static function create(string $uuid, string $name, ?string $description, array $lines): self
    {
        $self = new self();
        $self->uuid = Uuid::fromString($uuid)->toString();
        $self->replaceDefinition($name, $description, $lines);
        return $self;
    }

    /** Definitions are validated and catalog snapshots are resolved by the application layer. */
    public function replaceDefinition(string $name, ?string $description, array $lines): void
    {
        if (trim($name) === '' || mb_strlen($name) > 200 || ($description !== null && mb_strlen($description) > 4000) || $lines === [] || count($lines) > 100) {
            throw new BusinessRuleViolation('Template requires a name, 1..100 lines and description up to 4000 characters.');
        }
        $keys = [];
        foreach ($lines as $line) {
            if (!in_array($line['kind'] ?? null, ['equipment', 'consumable'], true) || !Uuid::isValid($line['itemUuid'] ?? '')) {
                throw new BusinessRuleViolation('Invalid template item.');
            }
            if (($line['sourceLocation'] !== null && mb_strlen($line['sourceLocation']) > 200) || ($line['instructions'] !== null && mb_strlen($line['instructions']) > 2000)) {
                throw new BusinessRuleViolation('Template source location maximum 200; instructions maximum 2000 characters.');
            }
            $key = $line['kind'].':'.$line['itemUuid'];
            if (isset($keys[$key])) {
                throw new BusinessRuleViolation('Template items must be unique.');
            }
            $keys[$key] = true;
            $rule = PreparationRule::restore($line['rule']);
            // Validate both the catalog unit and numeric limits without guessing guest counts.
            if ($rule->mode === 'fixed') {
                $rule->calculate(null, $line['unit']);
            }
        }
        $this->name = trim($name);
        $this->description = $description;
        $this->lines = $lines;
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
    public function isActive(): bool
    {
        return $this->active;
    }
    public function lines(): array
    {
        return $this->lines;
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'description' => $this->description, 'lines' => $this->lines, 'active' => $this->active, 'version' => $this->version, 'updatedAt' => $this->updatedAt->format(DATE_ATOM)];
    }
}
