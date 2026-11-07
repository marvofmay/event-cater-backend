<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Command\RemoveEventStaff;
use App\Module\Event\Application\Service\EventAssignmentNotifier;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\StaffAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveEventStaffHandler
{
    public function __construct(
        private EventEditor $editor,
        private EntityManagerInterface $em,
        private EventAssignmentNotifier $notifier,
    ) {
    }

    public function __invoke(RemoveEventStaff $command): void
    {
        $employeeUuid = $command->employeeUuid;
        $eventTitle = '';
        $eventDate = '';

        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'staff_removed', function (CateringEvent $event) use ($command, &$employeeUuid, &$eventTitle, &$eventDate): array {
            $assignment = $this->em->getRepository(StaffAssignment::class)->findOneBy(['event' => $event, 'employeeUuid' => $command->employeeUuid]);
            if (!$assignment) {
                throw new PlanningResourceNotFound('Staff assignment not found.');
            }
            $details = $assignment->toArray();
            $employeeUuid = $assignment->employeeUuid();
            $snapshot = $event->toArray();
            $eventTitle = (string) $snapshot['title'];
            $eventDate = (string) $snapshot['date'];
            $this->em->remove($assignment);

            return $details;
        });

        $this->notifier->staffRemoved($employeeUuid, $eventTitle, $eventDate, $command->uuid);
    }
}
