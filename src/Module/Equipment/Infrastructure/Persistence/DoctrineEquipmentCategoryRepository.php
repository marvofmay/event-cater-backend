<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Equipment\Domain\Entity\EquipmentCategory;
use App\Module\Equipment\Domain\Repository\EquipmentCategoryRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEquipmentCategoryRepository implements EquipmentCategoryRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function add(EquipmentCategory $category): void
    {
        $this->em->persist($category);
    }

    public function getForUpdate(string $uuid, ?int $version = null): EquipmentCategory
    {
        $category = $this->em->find(EquipmentCategory::class, $uuid);
        if (!$category) {
            throw new PlanningResourceNotFound('Nie znaleziono kategorii sprzętu.');
        }
        $this->em->refresh($category, LockMode::PESSIMISTIC_WRITE);
        if ($version !== null && $category->version() !== $version) {
            throw new PlanningConflict('Kategoria została zmieniona. Odśwież listę przed zapisem.');
        }
        return $category;
    }

    public function findByName(string $name): ?EquipmentCategory
    {
        return $this->em->getRepository(EquipmentCategory::class)->findOneBy(['name' => trim($name)]);
    }

    public function assertNameAvailable(string $name, string $exceptUuid): void
    {
        if ((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment_category WHERE name = ? AND uuid <> ?', [trim($name), $exceptUuid]) > 0) {
            throw new PlanningConflict('Kategoria o tej nazwie już istnieje.');
        }
    }

    public function remove(EquipmentCategory $category): void
    {
        $this->em->remove($category);
    }
}
