<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Equipment\Domain\Entity\EquipmentItem;
use App\Module\Equipment\Domain\Repository\EquipmentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEquipmentRepository implements EquipmentRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function add(EquipmentItem $item): void
    {
        $this->em->persist($item);
    }

    public function remove(EquipmentItem $item): void
    {
        $this->em->remove($item);
    }

    public function getForUpdate(string $uuid, int $expectedVersion): EquipmentItem
    {
        $item = $this->em->find(EquipmentItem::class, $uuid);
        if (!$item) {
            throw new PlanningResourceNotFound('Equipment not found.');
        }
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);
        if ($item->version() !== $expectedVersion) {
            throw new PlanningConflict('Stale version. Reload the resource before saving.');
        }
        return $item;
    }

    public function assertCodeAvailable(string $code, string $exceptUuid): void
    {
        $count = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment_item WHERE code = ? AND uuid <> ?', [strtoupper(trim($code)), $exceptUuid]);
        if ((int) $count > 0) {
            throw new PlanningConflict('Equipment code already exists.');
        }
    }

}
