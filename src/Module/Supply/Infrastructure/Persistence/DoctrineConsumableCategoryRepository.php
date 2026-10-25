<?php

declare(strict_types=1);

namespace App\Module\Supply\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Supply\Domain\Entity\ConsumableCategory;
use App\Module\Supply\Domain\Repository\ConsumableCategoryRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineConsumableCategoryRepository implements ConsumableCategoryRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function add(ConsumableCategory $category): void
    {
        $this->em->persist($category);
    }

    public function getForUpdate(string $uuid, ?int $version = null): ConsumableCategory
    {
        $category = $this->em->find(ConsumableCategory::class, $uuid);
        if (!$category) {
            throw new PlanningResourceNotFound('Nie znaleziono kategorii produktów.');
        }
        $this->em->refresh($category, LockMode::PESSIMISTIC_WRITE);
        if ($version !== null && $category->version() !== $version) {
            throw new PlanningConflict('Kategoria została zmieniona. Odśwież listę przed zapisem.');
        }
        return $category;
    }

    public function findByName(string $name): ?ConsumableCategory
    {
        return $this->em->getRepository(ConsumableCategory::class)->findOneBy(['name' => trim($name)]);
    }

    public function assertNameAvailable(string $name, string $exceptUuid): void
    {
        if ((int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM consumable_category WHERE name = ? AND uuid <> ?', [trim($name), $exceptUuid]) > 0) {
            throw new PlanningConflict('Kategoria o tej nazwie już istnieje.');
        }
    }

    public function remove(ConsumableCategory $category): void
    {
        $this->em->remove($category);
    }
}
