<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\CreateEventCustomer;
use App\Module\Event\Domain\Entity\EventCustomer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateEventCustomerHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(CreateEventCustomer $command): void
    {
        $this->em->persist(new EventCustomer($command->uuid, $command->name, $command->nip, $command->regon, $command->krs, $command->address, $command->phone, $command->email, $command->city, $command->street, $command->postalCode, $command->country));
    }
}
