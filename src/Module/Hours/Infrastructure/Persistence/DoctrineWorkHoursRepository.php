<?php

declare(strict_types=1);

namespace App\Module\Hours\Infrastructure\Persistence;

use App\Module\Hours\Domain\Entity\WorkHoursEntry;
use App\Module\Hours\Domain\Repository\WorkHoursRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWorkHoursRepository implements WorkHoursRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $uuid): ?WorkHoursEntry
    {
        return $this->em->find(WorkHoursEntry::class, $uuid);
    }

    public function add(WorkHoursEntry $entry): void
    {
        $this->em->persist($entry);
        $this->em->flush();
    }

    public function remove(WorkHoursEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }
}
