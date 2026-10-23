<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Kitchen\Domain\Enum\KitchenStatus;
use App\Module\Kitchen\Domain\ValueObject\KitchenPlan;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'kitchen_order')]
#[ORM\Index(name: 'kitchen_daily_plan', columns: ['production_date', 'status'])]
class KitchenOrder
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\Column(length: 36, unique: true)] private string $eventUuid;
    #[ORM\Column(length: 40, unique: true)] private string $number;
    #[ORM\Column(length: 20, enumType: KitchenStatus::class)] private KitchenStatus $status = KitchenStatus::DRAFT;
    #[ORM\Version] #[ORM\Column(type: 'integer')] private int $version = 1;
    #[ORM\Column(type: 'integer')] private int $revision = 0;
    #[ORM\Column(type: 'json')] private array $source;
    #[ORM\Column(length: 64)] private string $sourceHash;
    #[ORM\Column(length: 10)] private string $productionDate;
    #[ORM\Column(type: 'json')] private array $plan;
    #[ORM\Column(type: 'json')] private array $progress = [];
    #[ORM\Column(type: 'json', nullable: true)] private ?array $pendingRevision = null;
    #[ORM\Column(length: 36, nullable: true)] private ?string $releasedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $releasedAt = null;
    #[ORM\Column(length: 36, nullable: true)] private ?string $handedOverBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $handedOverAt = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $cancellationReason = null;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }
    public static function create(string $uuid, array $source, KitchenPlan $plan): self
    {
        $self = new self();
        $self->uuid = Uuid::fromString($uuid)->toString();
        $self->eventUuid = $source['eventUuid'];
        $self->number = 'KIT-'.strtoupper(str_replace('-', '', $uuid));
        $self->createdAt = self::now();
        $self->setPlan($plan->toArray(), $source);
        return $self;
    }
    public static function sourceHash(array $source): string
    {
        // Event optimistic version alone also changes for equipment/media; production uses only its explicit snapshot.
        unset($source['eventVersion'], $source['eventStatus']);
        return hash('sha256', json_encode(self::canonical($source), JSON_THROW_ON_ERROR));
    }
    private static function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item);
            }
        }
        return $value;
    }
    public function sourceChanged(array $source): bool
    {
        return $this->sourceHash !== self::sourceHash($source);
    }
    public function replaceDraft(KitchenPlan $plan, array $source): void
    {
        $this->requireStatus(KitchenStatus::DRAFT);
        $this->setPlan($plan->toArray(), $source);
    }
    private function setPlan(array $plan, array $source): void
    {
        $ids = array_column($plan['lines'], 'uuid');
        foreach ($this->progress as $uuid => $actual) {
            if (!in_array($uuid, $ids, true) && ($actual['preparedPortions'] > 0 || $actual['handedOverPortions'] > 0)) {
                throw new BusinessRuleViolation('A line with actual production cannot be removed; retain its UUID with requiredPortions 0.');
            }
        }
        $oldLines = isset($this->plan) ? array_column($this->plan['lines'], null, 'uuid') : [];
        foreach ($plan['lines'] as $line) {
            $actual = $this->progress[$line['uuid']] ?? self::emptyProgress();
            if (($actual['preparedPortions'] > 0 || $actual['handedOverPortions'] > 0) && isset($oldLines[$line['uuid']])) {
                foreach (['name', 'portionSize', 'diets', 'allergens'] as $identity) {
                    if ($oldLines[$line['uuid']][$identity] !== $line[$identity]) {
                        throw new BusinessRuleViolation('Produced dish identity cannot change; retain the old line and add a new UUID for the new dish/variant.');
                    }
                }
            }
        }
        $this->progress = array_intersect_key($this->progress, array_flip($ids));
        $this->plan = $plan;
        $this->productionDate = $plan['productionDate'];
        $this->source = $source;
        $this->sourceHash = self::sourceHash($source);
        $this->touch();
    }
    private function assertReleaseable(array $plan, array $source): void
    {
        if ($plan['responsibleUuid'] === null || $plan['dueAt'] === null || $source['guestCount'] === null
            || array_sum(array_column($plan['lines'], 'requiredPortions')) === 0) {
            throw new BusinessRuleViolation('Release requires a responsible employee, deadline, guest count and positive portions.');
        }
        $due = new \DateTimeImmutable($plan['dueAt']);
        if ($source['readyAt'] !== null && $due > new \DateTimeImmutable($source['readyAt'])) {
            throw new BusinessRuleViolation('Kitchen deadline cannot be after catering readiness.');
        }
        if ($plan['productionDate'] > $source['date']) {
            throw new BusinessRuleViolation('Production date cannot be after the event date.');
        }
    }
    public function release(array $source, string $actor): void
    {
        $this->requireStatus(KitchenStatus::DRAFT);
        if ($this->sourceChanged($source)) {
            throw new PlanningConflict('Event changed. Refresh the draft before releasing.');
        }
        $this->assertReleaseable($this->plan, $source);
        $this->status = KitchenStatus::RELEASED;
        $this->revision = 1;
        $this->releasedBy = $actor;
        $this->releasedAt = self::now();
        $this->touch();
    }
    public function propose(KitchenPlan $plan, array $source, string $reason, string $actor): void
    {
        if (in_array($this->status, [KitchenStatus::DRAFT, KitchenStatus::CANCELLED], true)) {
            throw new BusinessRuleViolation('Revisions require a released, noncancelled order.');
        }
        if ($this->pendingRevision !== null) {
            throw new PlanningConflict('Resolve the existing revision first.');
        }
        self::reason($reason);
        $next = $plan->toArray();
        $this->assertReleaseable($next, $source);
        $ids = array_column($next['lines'], 'uuid');
        foreach ($this->progress as $uuid => $actual) {
            if (!in_array($uuid, $ids, true) && ($actual['preparedPortions'] > 0 || $actual['handedOverPortions'] > 0)) {
                throw new BusinessRuleViolation('Retain produced lines explicitly, using requiredPortions 0 when discontinued.');
            }
        }
        $this->pendingRevision = ['number' => $this->revision + 1, 'plan' => $next, 'source' => $source,
            'reason' => $reason, 'proposedBy' => $actor, 'proposedAt' => self::now()->format(DATE_ATOM)];
        $this->touch();
    }
    public function acceptRevision(array $source, string $actor): void
    {
        $pending = $this->pendingRevision ?? throw new BusinessRuleViolation('No pending revision.');
        if (self::sourceHash($pending['source']) !== self::sourceHash($source)) {
            throw new PlanningConflict('Event changed since proposal; reject and propose an updated revision.');
        }
        $this->assertReleaseable($pending['plan'], $source);
        $this->setPlan($pending['plan'], $source);
        $this->revision = $pending['number'];
        $this->pendingRevision = null;
        $this->releasedBy = $actor;
        $this->releasedAt = self::now();
        $this->handedOverBy = null;
        $this->handedOverAt = null;
        $this->status = KitchenStatus::IN_PRODUCTION;
        $this->touch();
    }
    public function rejectRevision(string $reason): void
    {
        if ($this->pendingRevision === null) {
            throw new BusinessRuleViolation('No pending revision.');
        }
        self::reason($reason);
        $this->pendingRevision = null;
        $this->touch();
    }
    public function record(string $lineUuid, ?int $prepared, ?int $handed, ?string $reason, string $actor): array
    {
        if (in_array($this->status, [KitchenStatus::DRAFT, KitchenStatus::CANCELLED], true)) {
            throw new BusinessRuleViolation('Progress requires a released, noncancelled order.');
        }
        if (!in_array($lineUuid, array_column($this->plan['lines'], 'uuid'), true)) {
            throw new BusinessRuleViolation('Kitchen line does not belong to this order.');
        }
        if ($prepared === null && $handed === null) {
            throw new BusinessRuleViolation('Provide at least one production count.');
        }
        $before = $this->progress[$lineUuid] ?? self::emptyProgress();
        $prepared ??= $before['preparedPortions'];
        $handed ??= $before['handedOverPortions'];
        if ($prepared < 0 || $prepared > 1000000 || $handed < 0 || $handed > $prepared) {
            throw new BusinessRuleViolation('Counts must be 0..1000000 and handed over cannot exceed prepared.');
        }
        if ($prepared < $before['preparedPortions'] || $handed < $before['handedOverPortions']) {
            self::reason($reason);
        }
        $after = $before;
        if ($prepared !== $before['preparedPortions']) {
            $after['preparedPortions'] = $prepared;
            $after['preparedBy'] = $actor;
            $after['preparedAt'] = self::now()->format(DATE_ATOM);
        }
        if ($handed !== $before['handedOverPortions']) {
            $after['handedOverPortions'] = $handed;
            $after['handedOverBy'] = $actor;
            $after['handedOverAt'] = self::now()->format(DATE_ATOM);
        }
        $this->progress[$lineUuid] = $after;
        if ($before !== $after) {
            $this->status = $this->status === KitchenStatus::READY && $prepared === $before['preparedPortions'] ? KitchenStatus::READY : KitchenStatus::IN_PRODUCTION;
            $this->handedOverBy = null;
            $this->handedOverAt = null;
        }
        $this->touch();
        return ['lineUuid' => $lineUuid, 'before' => $before, 'after' => $after, 'correctionReason' => $reason];
    }
    public function start(array $source): void
    {
        $this->requireStatus(KitchenStatus::RELEASED);
        $this->assertCurrent($source);
        $this->status = KitchenStatus::IN_PRODUCTION;
        $this->touch();
    }
    public function ready(array $source): void
    {
        if (!in_array($this->status, [KitchenStatus::RELEASED, KitchenStatus::IN_PRODUCTION], true)) {
            throw new BusinessRuleViolation('Order is not in production.');
        }
        $this->assertCurrent($source);
        $this->assertCounts('preparedPortions');
        $this->status = KitchenStatus::READY;
        $this->touch();
    }
    public function handOver(array $source, string $actor): void
    {
        $this->requireStatus(KitchenStatus::READY);
        $this->assertCurrent($source);
        $this->assertCounts('handedOverPortions');
        $this->status = KitchenStatus::HANDED_OVER;
        $this->handedOverBy = $actor;
        $this->handedOverAt = self::now();
        $this->touch();
    }
    private function assertCurrent(array $source): void
    {
        if ($this->sourceChanged($source) || $this->pendingRevision !== null) {
            throw new PlanningConflict('Resolve menu changes and pending revisions before confirming readiness or handover.');
        }
    }
    private function assertCounts(string $field): void
    {
        foreach ($this->plan['lines'] as $line) {
            if (($this->progress[$line['uuid']][$field] ?? 0) < $line['requiredPortions']) {
                throw new BusinessRuleViolation('Required portions are incomplete.');
            }
        }
    }
    public function cancel(string $reason): void
    {
        if ($this->status === KitchenStatus::CANCELLED) {
            throw new BusinessRuleViolation('Order is already cancelled.');
        }
        self::reason($reason);
        $this->cancellationReason = $reason;
        $this->pendingRevision = null;
        $this->status = KitchenStatus::CANCELLED;
        $this->touch();
    }
    public static function reason(?string $reason): void
    {
        if ($reason === null || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) {
            throw new BusinessRuleViolation('A reason of 10..2000 characters is required.');
        }
    }
    private function requireStatus(KitchenStatus $status): void
    {
        if ($this->status !== $status) {
            throw new BusinessRuleViolation('Invalid kitchen status transition.');
        }
    }
    private static function emptyProgress(): array
    {
        return ['preparedPortions' => 0, 'handedOverPortions' => 0, 'preparedBy' => null, 'preparedAt' => null, 'handedOverBy' => null, 'handedOverAt' => null];
    }
    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    private function touch(): void
    {
        $this->updatedAt = self::now();
    }
    public function uuid(): string
    {
        return $this->uuid;
    }
    public function eventUuid(): string
    {
        return $this->eventUuid;
    }
    public function version(): int
    {
        return $this->version;
    }
    public function revision(): int
    {
        return $this->revision;
    }
    public function status(): KitchenStatus
    {
        return $this->status;
    }
    public function plan(): array
    {
        return $this->plan;
    }
    public function pending(): ?array
    {
        return $this->pendingRevision;
    }
    public function revisionSnapshot(): array
    {
        return ['source' => $this->source, 'plan' => $this->plan];
    }
    public function toArray(): array
    {
        $lines = [];
        foreach ($this->plan['lines'] as $line) {
            $actual = $this->progress[$line['uuid']] ?? self::emptyProgress();
            $lines[] = [...$line, ...$actual, 'missingPreparedPortions' => max(0, $line['requiredPortions'] - $actual['preparedPortions']),
                'missingHandedOverPortions' => max(0, $line['requiredPortions'] - $actual['handedOverPortions']), 'excessPreparedPortions' => max(0, $actual['preparedPortions'] - $line['requiredPortions'])];
        }
        return ['uuid' => $this->uuid, 'eventUuid' => $this->eventUuid, 'number' => $this->number, 'status' => $this->status->value,
            'version' => $this->version, 'revision' => $this->revision, 'source' => $this->source, ...$this->plan, 'productionDate' => $this->productionDate, 'lines' => $lines,
            'pendingRevision' => $this->pendingRevision, 'releasedBy' => $this->releasedBy, 'releasedAt' => $this->releasedAt?->format(DATE_ATOM),
            'handedOverBy' => $this->handedOverBy, 'handedOverAt' => $this->handedOverAt?->format(DATE_ATOM),
            'cancellationReason' => $this->cancellationReason, 'createdAt' => $this->createdAt->format(DATE_ATOM), 'updatedAt' => $this->updatedAt->format(DATE_ATOM)];
    }
}
