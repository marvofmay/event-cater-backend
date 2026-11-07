<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ConfigureEventPreparation;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventAssignmentNotifier;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\StaffAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfigureEventPreparationHandler
{
    public function __construct(
        private EventEditor $editor,
        private PreparationPlanner $planner,
        private EmployeeDirectory $employees,
        private EventAssignmentNotifier $notifier,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ConfigureEventPreparation $command): void
    {
        $previousResponsible = null;
        $eventTitle = '';
        $eventDate = '';
        $alsoOnStaff = false;

        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_configured', function (CateringEvent $event) use ($command, &$previousResponsible, &$eventTitle, &$eventDate, &$alsoOnStaff): array {
            $list = $this->planner->get($event);
            $before = $this->planner->checkpoint($list);
            $previousResponsible = $list->responsibleUuid();
            $name = $command->responsibleUuid === null ? null : $this->employees->requireActive($command->responsibleUuid);
            $list->configure($command->responsibleUuid, $name, $command->deadlineAt, $command->notes);
            $after = $this->planner->checkpoint($list);
            $snapshot = $event->toArray();
            $eventTitle = (string) $snapshot['title'];
            $eventDate = (string) $snapshot['date'];
            if ($command->responsibleUuid !== null) {
                $alsoOnStaff = $this->em->getRepository(StaffAssignment::class)->findOneBy([
                    'event' => $event,
                    'employeeUuid' => $command->responsibleUuid,
                ]) !== null;
            }

            return [...$after, 'before' => $before, 'after' => $after];
        });

        if (
            $command->responsibleUuid !== null
            && $command->responsibleUuid !== $previousResponsible
        ) {
            $this->notifier->packerAssigned($command->responsibleUuid, $eventTitle, $eventDate, $alsoOnStaff, $command->uuid);
        }
    }
}
