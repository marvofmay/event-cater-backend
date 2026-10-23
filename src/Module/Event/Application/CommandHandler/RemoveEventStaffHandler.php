<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Command\RemoveEventStaff;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\StaffAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveEventStaffHandler
{
    public function __construct(private EventEditor $editor, private EntityManagerInterface $em)
    {
    }
    public function __invoke(RemoveEventStaff $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'staff_removed', function (CateringEvent $event) use ($command): array {
            $assignment = $this->em->getRepository(StaffAssignment::class)->findOneBy(['event' => $event, 'employeeUuid' => $command->employeeUuid]);
            if (!$assignment) {
                throw new PlanningResourceNotFound('Staff assignment not found.');
            }
            $details = $assignment->toArray();
            $this->em->remove($assignment);
            return $details;
        });
    }
}
