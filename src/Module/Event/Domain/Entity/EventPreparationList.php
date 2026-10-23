<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\Enum\PreparationStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_preparation_list')]
class EventPreparationList
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\OneToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;
    /** @var Collection<int, PreparationLine> */
    #[ORM\OneToMany(targetEntity: PreparationLine::class, mappedBy: 'list', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC', 'uuid' => 'ASC'])]
    private Collection $lines;
    #[ORM\Column(length: 30, enumType: PreparationStatus::class)]
    private PreparationStatus $status = PreparationStatus::DRAFT;
    #[ORM\Column(type: 'integer')]
    private int $planRevision = 1;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $responsibleUuid = null;
    #[ORM\Column(length: 250, nullable: true)]
    private ?string $responsibleName = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deadlineAt = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $approvedRevision = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $approvedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $dispatchedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $acceptedShortagesReason = null;
    #[ORM\Column(type: 'json')]
    private array $acceptedShortages = [];

    public function __construct(CateringEvent $event)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->event = $event;
        $this->lines = new ArrayCollection();
    }

    public function configure(?string $responsibleUuid, ?string $responsibleName, ?\DateTimeImmutable $deadlineAt, ?string $notes): void
    {
        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new BusinessRuleViolation('Preparation notes exceed 4000 characters.');
        }
        if ($deadlineAt !== null) {
            $this->assertDeadline($deadlineAt);
        }
        $this->responsibleUuid = $responsibleUuid;
        $this->responsibleName = $responsibleName;
        $this->deadlineAt = $deadlineAt?->setTimezone(new \DateTimeZone('UTC'));
        $this->notes = $notes;
        $this->invalidatePlan();
    }

    public function assertDeadline(?\DateTimeImmutable $deadline = null): void
    {
        $deadline ??= $this->deadlineAt;
        if ($deadline !== null && $this->event->schedule()->readyAt !== null && $deadline > $this->event->schedule()->readyAt) {
            throw new BusinessRuleViolation('Preparation deadline cannot be later than catering readiness.');
        }
    }

    public function add(PreparationLine $line): void
    {
        if ($line->eventUuid() !== $this->eventUuid()) {
            throw new BusinessRuleViolation('Preparation line belongs to another event.');
        }
        if ($this->lines->count() >= 500 || $this->find($line->kind(), $line->itemUuid()) !== null) {
            throw new BusinessRuleViolation('Preparation supports up to 500 unique catalog items.');
        }
        $this->lines->add($line);
        $this->invalidatePlan();
    }

    public function remove(PreparationLine $line): void
    {
        $line->assertRemovable();
        $this->lines->removeElement($line);
        $this->invalidatePlan();
    }

    public function find(string $kind, string $itemUuid): ?PreparationLine
    {
        foreach ($this->lines as $line) {
            if ($line->kind() === $kind && $line->itemUuid() === $itemUuid) {
                return $line;
            }
        }
        return null;
    }

    public function line(string $uuid): ?PreparationLine
    {
        foreach ($this->lines as $line) {
            if ($line->uuid() === $uuid) {
                return $line;
            }
        }
        return null;
    }

    public function invalidatePlan(): void
    {
        ++$this->planRevision;
        $this->status = PreparationStatus::DRAFT;
        $this->approvedRevision = null;
        $this->approvedBy = null;
        $this->approvedAt = null;
        $this->clearDispatch();
    }

    public function approve(string $actorUuid): void
    {
        if ($this->status !== PreparationStatus::DRAFT || $this->lines->isEmpty() || $this->responsibleUuid === null) {
            throw new BusinessRuleViolation('Approve a nonempty draft with a responsible employee.');
        }
        $this->assertDeadline();
        $this->status = PreparationStatus::APPROVED;
        $this->approvedRevision = $this->planRevision;
        $this->approvedBy = $actorUuid;
        $this->approvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function start(): void
    {
        if ($this->status !== PreparationStatus::APPROVED) {
            throw new BusinessRuleViolation('Only an approved plan can start preparation.');
        }
        $this->status = PreparationStatus::IN_PREPARATION;
    }

    public function afterProgressChanged(): void
    {
        if ($this->status === PreparationStatus::DRAFT) {
            return;
        }
        $previous = $this->status;
        $this->clearDispatch();
        $this->status = in_array($previous, [PreparationStatus::READY_FOR_LOADING, PreparationStatus::LOADED], true) && $this->allPrepared()
            ? PreparationStatus::READY_FOR_LOADING : PreparationStatus::IN_PREPARATION;
    }

    public function markReady(): void
    {
        if (!in_array($this->status, [PreparationStatus::APPROVED, PreparationStatus::IN_PREPARATION], true) || !$this->allPrepared()) {
            throw new BusinessRuleViolation('All required quantities must be prepared before marking the list ready.');
        }
        $this->status = PreparationStatus::READY_FOR_LOADING;
    }

    public function confirmLoaded(string $actorUuid, ?string $acceptShortagesReason): void
    {
        if ($this->status === PreparationStatus::DRAFT || $this->status === PreparationStatus::LOADED || $this->lines->isEmpty() || $this->approvedRevision !== $this->planRevision) {
            throw new BusinessRuleViolation('Dispatch requires an approved, nonempty, not yet dispatched list.');
        }
        $shortages = array_values(array_filter(array_map(static fn (PreparationLine $line) => $line->toArray(), $this->lines->toArray()), static fn (array $line) => $line['missingToLoad'] !== '0.000'));
        if ($shortages !== [] && (mb_strlen(trim($acceptShortagesReason ?? '')) < 10 || mb_strlen($acceptShortagesReason ?? '') > 2000)) {
            throw new BusinessRuleViolation('Dispatch with shortages requires an explicit reason of 10..2000 characters.');
        }
        if ($shortages === [] && $acceptShortagesReason !== null) {
            throw new BusinessRuleViolation('There are no shortages to accept.');
        }
        $this->status = PreparationStatus::LOADED;
        $this->dispatchedBy = $actorUuid;
        $this->dispatchedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->acceptedShortagesReason = $acceptShortagesReason;
        $this->acceptedShortages = $shortages;
    }

    private function clearDispatch(): void
    {
        $this->dispatchedBy = null;
        $this->dispatchedAt = null;
        $this->acceptedShortagesReason = null;
        $this->acceptedShortages = [];
    }

    private function allPrepared(): bool
    {
        if ($this->lines->isEmpty()) {
            return false;
        }
        foreach ($this->lines as $line) {
            if (!$line->missingToPrepare()->isZero()) {
                return false;
            }
        }
        return true;
    }

    public function eventUuid(): string
    {
        return $this->event->uuid();
    }
    public function status(): PreparationStatus
    {
        return $this->status;
    }
    public function isApproved(): bool
    {
        return $this->approvedRevision === $this->planRevision;
    }
    public function responsibleUuid(): ?string
    {
        return $this->responsibleUuid;
    }
    /** @return list<PreparationLine> */
    public function lines(): array
    {
        return array_values($this->lines->toArray());
    }
    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public function toArray(): array
    {
        $lines = array_map(static fn (PreparationLine $line) => $line->toArray(), $this->lines());
        usort($lines, static fn (array $a, array $b) => [$a['name'], $a['uuid']] <=> [$b['name'], $b['uuid']]);
        $missingPreparation = count(array_filter($lines, static fn (array $line) => $line['missingToPrepare'] !== '0.000'));
        $missingLoading = count(array_filter($lines, static fn (array $line) => $line['missingToLoad'] !== '0.000'));
        return ['uuid' => $this->uuid, 'eventUuid' => $this->eventUuid(), 'version' => $this->event->version(), 'planRevision' => $this->planRevision, 'status' => $this->status->value,
            'responsibleUuid' => $this->responsibleUuid, 'responsibleName' => $this->responsibleName,
            'deadlineAt' => ($this->deadlineAt ?? $this->event->schedule()->readyAt)?->format(DATE_ATOM), 'configuredDeadlineAt' => $this->deadlineAt?->format(DATE_ATOM), 'notes' => $this->notes,
            'approvedRevision' => $this->approvedRevision, 'approvedBy' => $this->approvedBy, 'approvedAt' => $this->approvedAt?->format(DATE_ATOM),
            'dispatchedBy' => $this->dispatchedBy, 'dispatchedAt' => $this->dispatchedAt?->format(DATE_ATOM),
            'acceptedShortagesReason' => $this->acceptedShortagesReason, 'acceptedShortages' => $this->acceptedShortages,
            'summary' => ['totalLines' => count($lines), 'missingPreparationLines' => $missingPreparation, 'missingLoadingLines' => $missingLoading,
                'fullyPreparedLines' => count($lines) - $missingPreparation, 'fullyLoadedLines' => count($lines) - $missingLoading,
                'hasShortages' => $missingLoading > 0, 'hasSurplus' => count(array_filter($lines, static fn (array $line) => $line['extraPrepared'] !== '0.000' || $line['extraLoaded'] !== '0.000')) > 0],
            'lines' => $lines];
    }
}
