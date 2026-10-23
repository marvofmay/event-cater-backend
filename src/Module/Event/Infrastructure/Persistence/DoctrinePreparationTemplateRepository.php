<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Event\Domain\Entity\PreparationTemplate;
use App\Module\Event\Domain\Repository\PreparationTemplateRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePreparationTemplateRepository implements PreparationTemplateRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function add(PreparationTemplate $item): void
    {
        $this->em->persist($item);
    }
    public function getForApply(string $uuid, int $version): PreparationTemplate
    {
        return $this->getLocked($uuid, $version, LockMode::PESSIMISTIC_READ);
    }
    public function getForUpdate(string $uuid, int $version): PreparationTemplate
    {
        return $this->getLocked($uuid, $version, LockMode::PESSIMISTIC_WRITE);
    }
    private function getLocked(string $uuid, int $version, LockMode $mode): PreparationTemplate
    {
        $item = $this->em->find(PreparationTemplate::class, $uuid);
        if (!$item) {
            throw new PlanningResourceNotFound('PreparationTemplate not found.');
        }
        $this->em->refresh($item, $mode);
        if ($item->version() !== $version) {
            throw new PlanningConflict('Stale version. Reload before saving.');
        }
        return $item;
    }
}
