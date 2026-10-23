<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Repository\EventRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function add(CateringEvent $item): void
    {
        $this->em->persist($item);
    }

    public function getForUpdate(string $uuid, int $expectedVersion): CateringEvent
    {
        $item = $this->em->find(CateringEvent::class, $uuid);
        if (!$item) {
            throw new PlanningResourceNotFound('Event not found.');
        }
        $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE);
        if ($item->version() !== $expectedVersion) {
            throw new PlanningConflict('Stale version. Reload the resource before saving.');
        }
        return $item;
    }

}
