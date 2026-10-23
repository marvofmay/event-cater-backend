<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Kitchen\Application\Port\EventProductionSource;
use App\Module\Kitchen\Application\Query\GetEventKitchenOrder;
use App\Module\Kitchen\Application\Query\GetKitchenHistory;
use App\Module\Kitchen\Application\Query\GetKitchenOrder;
use App\Module\Kitchen\Application\Query\GetKitchenRevision;
use App\Module\Kitchen\Application\Query\ListKitchenOrders;
use App\Module\Kitchen\Domain\Entity\KitchenHistoryEntry;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use App\Module\Kitchen\Domain\Entity\KitchenRevision;
use App\Module\Kitchen\Domain\Enum\KitchenStatus;
use App\Module\Kitchen\Domain\ValueObject\KitchenPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class KitchenQueryHandler
{
    public function __construct(private EntityManagerInterface $em, private EventProductionSource $events, private \App\Module\Kitchen\Application\Port\KitchenStaffDirectory $staff)
    {
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function staff(\App\Module\Kitchen\Application\Query\SearchKitchenStaff $q): array
    {
        return ['items' => $this->staff->search($q->search)];
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetKitchenOrder $q): array
    {
        $order = $this->requireOrder($q->uuid);
        return $this->project($order, $this->events->get($order->eventUuid()));
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function event(GetEventKitchenOrder $q): array
    {
        $this->events->get($q->eventUuid);
        $order = $this->em->getRepository(KitchenOrder::class)->findOneBy(['eventUuid' => $q->eventUuid]);
        if (!$order) {
            throw new PlanningResourceNotFound('Kitchen order not found for this event.');
        }
        return $this->get(new GetKitchenOrder($order->uuid()));
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListKitchenOrders $q): array
    {
        KitchenPlan::validateDate($q->date);
        if ($q->status !== null && KitchenStatus::tryFrom($q->status) === null) {
            throw new BusinessRuleViolation('Invalid kitchen status.');
        }
        $criteria = ['productionDate' => $q->date];
        if ($q->status !== null) {
            $criteria['status'] = $q->status;
        }
        $repo = $this->em->getRepository(KitchenOrder::class);
        $orders = $repo->findBy($criteria, ['number' => 'ASC'], $q->page->limit, $q->page->offset());
        // Batch the current event snapshots for this page, avoiding a query per card/line.
        $sources = [];
        if ($orders !== []) {
            $ids = array_map(static fn (KitchenOrder $o) => $o->eventUuid(), $orders);
            // Source adapter exposes a batch port, keeping Event persistence out of Kitchen projections.
            $sources = $this->events->getMany($ids);
        }
        $items = array_map(fn (KitchenOrder $o) => $this->project($o, $sources[$o->eventUuid()]), $orders);
        return $q->page->result($items, $repo->count($criteria));
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function history(GetKitchenHistory $q): array
    {
        $this->requireOrder($q->uuid);
        $repo = $this->em->getRepository(KitchenHistoryEntry::class);
        $rows = $repo->findBy(['order' => $q->uuid], ['orderVersion' => 'DESC'], $q->page->limit, $q->page->offset());
        return $q->page->result(array_map(static fn (KitchenHistoryEntry $h) => $h->toArray(), $rows), $repo->count(['order' => $q->uuid]));
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function revision(GetKitchenRevision $q): array
    {
        $order = $this->requireOrder($q->uuid);
        $row = $this->em->getRepository(KitchenRevision::class)->findOneBy(['order' => $q->uuid, 'number' => $q->number]);
        if (!$row) {
            throw new PlanningResourceNotFound('Kitchen revision not found.');
        }
        $source = $this->events->get($order->eventUuid());
        return [...$row->toArray(), 'orderNumber' => $order->toArray()['number'], 'currentRevision' => $order->revision(),
            'superseded' => $q->number !== $order->revision(), 'orderStatus' => $order->status()->value,
            'sourceChanged' => $order->sourceChanged($source), 'eventStatus' => $source['eventStatus'], 'pendingRevision' => $order->pending() !== null];
    }
    private function project(KitchenOrder $order, array $source): array
    {
        return [...$order->toArray(), 'sourceChanged' => $order->sourceChanged($source), 'currentSource' => $source,
            'productionBlocked' => in_array($source['eventStatus'], ['completed', 'cancelled'], true) || $order->status() === KitchenStatus::CANCELLED,
            'revisionDiff' => $order->pending() === null ? null : $this->diff($order->plan(), $order->pending()['plan'])];
    }
    private function diff(array $before, array $after): array
    {
        $old = array_column($before['lines'], null, 'uuid');
        $new = array_column($after['lines'], null, 'uuid');
        $added = [];
        $removed = [];
        $changed = [];
        foreach ($new as $id => $line) {
            if (!isset($old[$id])) {
                $added[] = $line;
            } elseif ($old[$id] != $line) {
                $changed[] = ['uuid' => $id, 'before' => $old[$id], 'after' => $line, 'portionsDelta' => $line['requiredPortions'] - $old[$id]['requiredPortions']];
            }
        }
        foreach ($old as $id => $line) {
            if (!isset($new[$id])) {
                $removed[] = $line;
            }
        }
        return ['added' => $added, 'removed' => $removed, 'changed' => $changed,
            'scheduleBefore' => array_diff_key($before, ['lines' => true]), 'scheduleAfter' => array_diff_key($after, ['lines' => true])];
    }
    private function requireOrder(string $uuid): KitchenOrder
    {
        return $this->em->find(KitchenOrder::class, $uuid) ?? throw new PlanningResourceNotFound('Kitchen order not found.');
    }
}
