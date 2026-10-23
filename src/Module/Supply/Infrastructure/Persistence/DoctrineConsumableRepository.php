<?php

declare(strict_types=1);

namespace App\Module\Supply\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Supply\Domain\Entity\ConsumableItem;
use App\Module\Supply\Domain\Repository\ConsumableRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineConsumableRepository implements ConsumableRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function add(ConsumableItem $item): void
    {
        $this->em->persist($item);
    }
    public function getForUpdate(string $uuid, int $version): ConsumableItem
    {
        $item = $this->em->find(ConsumableItem::class, $uuid);
        if (!$item) {
            throw new PlanningResourceNotFound('ConsumableItem not found.');
        }
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);
        if ($item->version() !== $version) {
            throw new PlanningConflict('Stale version. Reload before saving.');
        }
        return $item;
    }
}
