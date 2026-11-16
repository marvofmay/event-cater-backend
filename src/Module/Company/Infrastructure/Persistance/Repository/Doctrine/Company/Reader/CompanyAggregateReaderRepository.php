<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\Persistance\Repository\Doctrine\Company\Reader;

use App\Common\Domain\Entity\EventStore;
use App\Module\Company\Domain\Aggregate\Company\CompanyAggregate;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Interface\Company\CompanyAggregateReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\SerializerInterface;

final class CompanyAggregateReaderRepository extends ServiceEntityRepository implements CompanyAggregateReaderInterface
{
    public function __construct(ManagerRegistry $registry, private SerializerInterface $serializer)
    {
        parent::__construct($registry, EventStore::class);
    }

    public function getCompanyAggregateByUUID(CompanyUUID $uuid): CompanyAggregate
    {
        $events = $this->findBy([
            'aggregateUUID' => $uuid->toString(),
            'aggregateClass' => CompanyAggregate::class,
        ], ['createdAt' => 'ASC', 'createdAtUs' => 'ASC']);

        if (empty($events)) {
            throw new \RuntimeException('Aggregate company not found: '.$uuid->toString());
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

        return CompanyAggregate::reconstituteFromHistory($domainEvents);
    }
}
