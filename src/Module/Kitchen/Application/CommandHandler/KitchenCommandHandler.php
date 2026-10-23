<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Kitchen\Application\Command\AcceptKitchenRevision;
use App\Module\Kitchen\Application\Command\CreateKitchenOrder;
use App\Module\Kitchen\Application\Command\ProposeKitchenRevision;
use App\Module\Kitchen\Application\Command\RecordKitchenProgress;
use App\Module\Kitchen\Application\Command\RejectKitchenRevision;
use App\Module\Kitchen\Application\Command\TransitionKitchenOrder;
use App\Module\Kitchen\Application\Command\UpdateKitchenDraft;
use App\Module\Kitchen\Application\Port\EventProductionSource;
use App\Module\Kitchen\Application\Service\KitchenPlanResolver;
use App\Module\Kitchen\Domain\Entity\KitchenHistoryEntry;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use App\Module\Kitchen\Domain\Entity\KitchenRevision;
use App\Module\Kitchen\Domain\Repository\KitchenOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class KitchenCommandHandler
{
    public function __construct(private KitchenOrderRepository $orders, private EventProductionSource $events, private KitchenPlanResolver $plans, private EntityManagerInterface $em)
    {
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function create(CreateKitchenOrder $c): void
    {
        $source = $this->events->get($c->eventUuid, true);
        $this->assertEventOpen($source);
        $this->assertEventVersion($source, $c->eventVersion);
        $plan = $this->plans->fromMenu($source, $c->productionDate, $c->dueAt, $c->responsibleUuid, $c->notes, $c->serviceMode);
        $order = KitchenOrder::create($c->uuid, $source, $plan);
        $this->orders->add($order);
        $this->audit($order, 'created', $order->revisionSnapshot(), $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateKitchenDraft $c): void
    {
        [$order, $source] = $this->load($c->uuid, $c->version);
        $this->assertEventVersion($source, $c->eventVersion);
        $before = $order->revisionSnapshot();
        $order->replaceDraft($this->plans->resolve($c->plan), $source);
        $this->audit($order, 'draft_updated', ['before' => $before, 'after' => $order->revisionSnapshot()], $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function propose(ProposeKitchenRevision $c): void
    {
        [$order, $source] = $this->load($c->uuid, $c->version);
        $this->assertEventVersion($source, $c->eventVersion);
        $order->propose($this->plans->resolve($c->plan), $source, $c->reason, $c->actorUuid);
        $this->audit($order, 'revision_proposed', $order->pending(), $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function accept(AcceptKitchenRevision $c): void
    {
        [$order, $source] = $this->load($c->uuid, $c->version);
        $pending = $order->pending() ?? throw new BusinessRuleViolation('No pending revision.');
        $this->plans->revalidate($pending['plan']);
        $order->acceptRevision($source, $c->actorUuid);
        $this->saveRevision($order, $c->actorUuid);
        $this->audit($order, 'revision_accepted', ['revision' => $order->revision(), 'reason' => $pending['reason'], 'proposedBy' => $pending['proposedBy']], $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function reject(RejectKitchenRevision $c): void
    {
        [$order] = $this->load($c->uuid, $c->version, true);
        $pending = $order->pending();
        $order->rejectRevision($c->reason);
        $this->audit($order, 'revision_rejected', ['proposal' => $pending, 'reason' => $c->reason], $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function progress(RecordKitchenProgress $c): void
    {
        [$order] = $this->load($c->uuid, $c->version);
        if (!in_array($c->lineUuid, array_column($order->plan()['lines'], 'uuid'), true)) {
            throw new \App\Common\Application\Exception\PlanningResourceNotFound('Kitchen line not found.');
        }
        $details = $order->record($c->lineUuid, $c->preparedPortions, $c->handedOverPortions, $c->correctionReason, $c->actorUuid);
        $this->audit($order, 'progress_recorded', $details, $c->actorUuid);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function transition(TransitionKitchenOrder $c): void
    {
        [$order, $source] = $this->load($c->uuid, $c->version, $c->action === 'cancel');
        $before = $order->status()->value;
        switch ($c->action) {
            case 'release': $this->plans->revalidate($order->plan());
                $order->release($source, $c->actorUuid);
                $this->saveRevision($order, $c->actorUuid);
                break;
            case 'start': $order->start($source);
                break;
            case 'ready': $order->ready($source);
                break;
            case 'hand-over': $this->plans->revalidate($order->plan());
                $order->handOver($source, $c->actorUuid);
                break;
            case 'cancel': $order->cancel($c->reason ?? '');
                break;
            default: throw new BusinessRuleViolation('Unknown kitchen action.');
        }
        $this->audit($order, $c->action, ['from' => $before, 'to' => $order->status()->value, 'revision' => $order->revision(), 'reason' => $c->reason], $c->actorUuid);
    }
    private function load(string $uuid, int $version, bool $allowClosed = false): array
    {
        // Lock the event first everywhere; there is no second, independent copy of event edit state.
        $source = $this->events->get($this->orders->eventUuid($uuid), true);
        if (!$allowClosed) {
            $this->assertEventOpen($source);
        }
        return [$this->orders->getForUpdate($uuid, $version), $source];
    }
    private function assertEventOpen(array $source): void
    {
        if (in_array($source['eventStatus'], ['completed', 'cancelled'], true)) {
            throw new BusinessRuleViolation('Production is blocked for completed/cancelled events; cancel the kitchen order explicitly to retain its history.');
        }
    }
    private function assertEventVersion(array $source, int $expected): void
    {
        if ($source['eventVersion'] !== $expected) {
            throw new PlanningConflict('Stale event version. Reload the event before importing menu changes.');
        }
    }
    private function saveRevision(KitchenOrder $order, string $actor): void
    {
        $this->em->persist(new KitchenRevision($order, $order->revision(), $order->revisionSnapshot(), $actor));
    }
    private function audit(KitchenOrder $order, string $action, array $details, string $actor): void
    {
        $this->em->persist(new KitchenHistoryEntry($order, $action, $details, $actor));
    }
}
