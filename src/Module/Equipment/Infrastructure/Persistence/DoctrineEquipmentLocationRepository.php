<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Equipment\Domain\Entity\EquipmentLocation;
use App\Module\Equipment\Domain\Repository\EquipmentLocationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEquipmentLocationRepository implements EquipmentLocationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function add(EquipmentLocation $category): void
    {
        $this->em->persist($category);
    }

    public function getForUpdate(string $uuid, ?int $version = null): EquipmentLocation
    {
        $category = $this->em->find(EquipmentLocation::class, $uuid);
        if (!$category) {
            throw new PlanningResourceNotFound('Nie znaleziono miejsca przechowywania.');
        }
        $this->em->refresh($category, LockMode::PESSIMISTIC_WRITE);
        if ($version !== null && $category->version() !== $version) {
            throw new PlanningConflict('Lokalizacja została zmieniona. Odśwież listę przed zapisem.');
        }
        return $category;
    }

    public function findByName(string $name): ?EquipmentLocation
    {
        return $this->em->getRepository(EquipmentLocation::class)->findOneBy(['name' => trim($name)]);
    }

    public function assertNameAvailable(string $name, string $exceptUuid): void
    {
        if ((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment_location WHERE name = ? AND uuid <> ?', [trim($name), $exceptUuid]) > 0) {
            throw new PlanningConflict('Miejsce o tej nazwie już istnieje.');
        }
    }

    public function remove(EquipmentLocation $category): void
    {
        $this->em->remove($category);
    }
}
