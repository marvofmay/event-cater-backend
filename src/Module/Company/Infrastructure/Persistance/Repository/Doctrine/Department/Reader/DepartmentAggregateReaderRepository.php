<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\Persistance\Repository\Doctrine\Department\Reader;

use App\Common\Domain\Entity\EventStore;
use App\Module\Company\Domain\Aggregate\Department\DepartmentAggregate;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Interface\Department\DepartmentAggregateReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\SerializerInterface;

final class DepartmentAggregateReaderRepository extends ServiceEntityRepository implements DepartmentAggregateReaderInterface
{
    public function __construct(ManagerRegistry $registry, private SerializerInterface $serializer)
    {
        parent::__construct($registry, EventStore::class);
    }

    public function getDepartmentAggregateByUUID(DepartmentUUID $uuid): DepartmentAggregate
    {
        $events = $this->findBy([
            'aggregateUUID' => $uuid->toString(),
            'aggregateClass' => DepartmentAggregate::class,
        ], ['createdAt' => 'ASC', 'createdAtUs' => 'ASC']);

        if (empty($events)) {
            throw new \RuntimeException('Aggregate not found: '.$uuid->toString());
        }

        $domainEvents = [];

        foreach ($events as $eventEntity) {
            $event = $this->serializer->deserialize(
                $eventEntity->getPayload(),
                $eventEntity->getAggregateType(),
                'json'
            );

            $domainEvents[] = $event;
        }

        return DepartmentAggregate::reconstituteFromHistory($domainEvents);
    }
}
