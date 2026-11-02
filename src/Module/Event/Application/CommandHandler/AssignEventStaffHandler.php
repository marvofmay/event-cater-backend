<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\AssignEventStaff;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventAssignmentNotifier;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\StaffPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\StaffAssignment;
use App\Module\Event\Domain\Enum\EventFunction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AssignEventStaffHandler
{
    private const FUNCTION_LABELS = [
        'driver' => 'Kierowca',
        'waiter' => 'Kelner / kelnerka',
        'logistics' => 'Logistyka',
        'chef' => 'Kucharz',
        'coordinator' => 'Koordynator',
    ];

    public function __construct(
        private EventEditor $editor,
        private EmployeeDirectory $employees,
        private StaffPlanner $staff,
        private EntityManagerInterface $em,
        private EventAssignmentNotifier $notifier,
    ) {
    }

    public function __invoke(AssignEventStaff $command): void
    {
        $isNew = false;
        $eventTitle = '';
        $eventDate = '';
        $functionLabels = [];

        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'staff_assigned', function (CateringEvent $event) use ($command, &$isNew, &$eventTitle, &$eventDate, &$functionLabels): array {
            $zone = new \DateTimeZone($event->schedule()->timezone);
            $name = $this->employees->requireActive($command->employeeUuid, $command->startsAt->setTimezone($zone), $command->endsAt->setTimezone($zone));
            $this->staff->assertAvailable($command->employeeUuid, $event->uuid(), $command->startsAt, $command->endsAt);
            $assignment = $this->em->getRepository(StaffAssignment::class)->findOneBy(['event' => $event, 'employeeUuid' => $command->employeeUuid]);
            $isNew = $assignment === null;
            if ($assignment) {
                $assignment->update($name, $command->functions, $command->startsAt, $command->endsAt, $command->notes);
            } else {
                $assignment = new StaffAssignment($event, $command->employeeUuid, $name, $command->functions, $command->startsAt, $command->endsAt, $command->notes);
                $this->em->persist($assignment);
            }
            $snapshot = $event->toArray();
            $eventTitle = (string) $snapshot['title'];
            $eventDate = (string) $snapshot['date'];
            $functionLabels = array_values(array_filter(array_map(
                static fn (EventFunction $fn) => self::FUNCTION_LABELS[$fn->value] ?? $fn->value,
                $command->functions,
            )));

            return $assignment->toArray();
        });

        if ($isNew) {
            $this->notifier->staffAssigned($command->employeeUuid, $eventTitle, $eventDate, $functionLabels);
        }
    }
}
