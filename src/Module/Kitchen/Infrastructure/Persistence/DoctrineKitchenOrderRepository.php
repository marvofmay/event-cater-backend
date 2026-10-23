<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use App\Module\Kitchen\Domain\Repository\KitchenOrderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineKitchenOrderRepository implements KitchenOrderRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function add(KitchenOrder $order): void
    {
        $this->em->persist($order);
    }
    public function eventUuid(string $uuid): string
    {
        $value = $this->em->getConnection()->fetchOne('SELECT event_uuid FROM kitchen_order WHERE uuid = ?', [$uuid]);
        return $value === false ? throw new PlanningResourceNotFound('Kitchen order not found.') : $value;
    }
    public function getForUpdate(string $uuid, int $version): KitchenOrder
    {
        $item = $this->em->find(KitchenOrder::class, $uuid) ?? throw new PlanningResourceNotFound('Kitchen order not found.');
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);
        if ($item->version() !== $version) {
            throw new PlanningConflict('Stale kitchen version. Reload before saving.');
        }
        return $item;
    }
}
