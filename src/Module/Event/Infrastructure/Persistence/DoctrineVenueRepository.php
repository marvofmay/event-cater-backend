<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Event\Domain\Entity\EventVenue;
use App\Module\Event\Domain\Repository\VenueRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineVenueRepository implements VenueRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function add(EventVenue $venue): void
    {
        $this->em->persist($venue);
    }
    public function get(string $uuid, ?int $version = null, bool $exclusive = false): EventVenue
    {
        $venue = $this->em->find(EventVenue::class, $uuid) ?? throw new PlanningResourceNotFound('Venue not found.');
        if ($version !== null) {
            $this->em->refresh($venue, $exclusive ? LockMode::PESSIMISTIC_WRITE : LockMode::PESSIMISTIC_READ);
            if ($venue->version() !== $version) {
                throw new PlanningConflict('Venue has changed. Reload its current version.');
            }
        }
        return $venue;
    }
}
