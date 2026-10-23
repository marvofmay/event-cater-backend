<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\CreateEvent;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventHistoryEntry;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateEventHandler
{
    public function __construct(private EventRepository $events, private EmployeeDirectory $employees, private EntityManagerInterface $em)
    {
    }
    public function __invoke(CreateEvent $command): void
    {
        $d = $command->details;
        if ($d->coordinatorUuid) {
            $this->employees->requireActive($d->coordinatorUuid);
        }
        $event = CateringEvent::create($command->uuid, $d->title, $d->schedule);
        $event->replaceDetails($d->title, $d->description, $d->schedule, $d->location, $d->guestCount, $d->coordinatorUuid, $d->customerName);
        $this->events->add($event);
        $this->em->persist(new EventPreparationList($event));
        $this->em->persist(new EventHistoryEntry($event, $command->actorUuid, 'created', $event->toArray()));
    }
}
