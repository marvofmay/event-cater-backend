<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\ReadModel;

use App\Common\Domain\Entity\EventStore;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedAvatarEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EmployeeAvatarReader
{
    public function __construct(private EntityManagerInterface $manager)
    {
    }

    public function forEmployees(array $employeeUUIDs): array
    {
        if ($employeeUUIDs === []) return [];
        $events = $this->manager->getRepository(EventStore::class)->findBy([
            'aggregateUUID' => $employeeUUIDs,
            'aggregateClass' => EmployeeAggregate::class,
            'aggregateType' => EmployeeChangedAvatarEvent::class,
        ], ['createdAt' => 'ASC']);
        $avatars = [];
        foreach ($events as $event) {
            $payload = json_decode($event->getPayload(), true, flags: JSON_THROW_ON_ERROR);
            $avatars[$event->getAggregateUUID()] = [
                'avatarType' => $payload['avatarType'],
                'defaultAvatar' => $payload['defaultAvatar'] ?? null,
                'avatarPath' => $payload['avatarPath'] ?? null,
            ];
        }

        return $avatars;
    }
}
