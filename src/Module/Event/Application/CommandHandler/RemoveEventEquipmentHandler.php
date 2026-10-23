<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Command\RemoveEventEquipment;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveEventEquipmentHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $preparation, private EntityManagerInterface $em)
    {
    }
    public function __invoke(RemoveEventEquipment $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'equipment_removed', function (CateringEvent $event) use ($command): array {
            $item = $this->em->getRepository(EquipmentRequirement::class)->findOneBy(['event' => $event, 'equipmentUuid' => $command->equipmentUuid]);
            if (!$item) {
                throw new PlanningResourceNotFound('Equipment requirement not found.');
            }
            $details = $item->toArray();
            $this->preparation->equipmentRemoved($event, $item);
            $this->em->remove($item);
            return $details;
        });
    }
}
