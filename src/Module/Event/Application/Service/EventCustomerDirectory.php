<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Domain\Entity\EventCustomer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventCustomerDirectory
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function name(?string $uuid, ?string $legacyName): ?string
    {
        if ($uuid === null) {
            return $legacyName;
        }
        $customer = $this->em->find(EventCustomer::class, $uuid) ?? throw new PlanningResourceNotFound('Nie znaleziono kontrahenta w tej firmie.');
        return $customer->toArray()['name'];
    }
}
