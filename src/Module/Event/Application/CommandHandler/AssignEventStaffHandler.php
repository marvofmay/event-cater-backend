<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\AssignEventStaff;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\StaffPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\StaffAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AssignEventStaffHandler
{
    public function __construct(private EventEditor $editor, private EmployeeDirectory $employees, private StaffPlanner $staff, private EntityManagerInterface $em)
    {
    }
    public function __invoke(AssignEventStaff $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'staff_assigned', function (CateringEvent $event) use ($command): array {
            $zone = new \DateTimeZone($event->schedule()->timezone);
            $name = $this->employees->requireActive($command->employeeUuid, $command->startsAt->setTimezone($zone), $command->endsAt->setTimezone($zone));
            $this->staff->assertAvailable($command->employeeUuid, $event->uuid(), $command->startsAt, $command->endsAt);
            $assignment = $this->em->getRepository(StaffAssignment::class)->findOneBy(['event' => $event, 'employeeUuid' => $command->employeeUuid]);
            if ($assignment) {
                $assignment->update($name, $command->functions, $command->startsAt, $command->endsAt, $command->notes);
            } else {
                $assignment = new StaffAssignment($event, $command->employeeUuid, $name, $command->functions, $command->startsAt, $command->endsAt, $command->notes);
                $this->em->persist($assignment);
            }
            return $assignment->toArray();
        });
    }
}
