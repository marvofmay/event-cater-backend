<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Repository\PreparationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePreparationRepository implements PreparationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getForEvent(CateringEvent $event): EventPreparationList
    {
        $query = $this->em->createQueryBuilder()->select('p', 'lines', 'equipment')
            ->from(EventPreparationList::class, 'p')->leftJoin('p.lines', 'lines')->leftJoin('lines.equipment', 'equipment')
            ->where('p.event = :event')->setParameter('event', $event)->getQuery();
        $list = $query->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if (!$list) {
            throw new PlanningResourceNotFound('Preparation list not found. Apply the preparation migration.');
        }
        return $list;
    }
}
