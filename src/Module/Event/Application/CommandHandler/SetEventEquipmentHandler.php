<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\SetEventEquipment;
use App\Module\Event\Application\Port\EquipmentCatalog;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetEventEquipmentHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $preparation, private EquipmentCatalog $catalog, private EntityManagerInterface $em)
    {
    }
    public function __invoke(SetEventEquipment $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'equipment_set', function (CateringEvent $event) use ($command): array {
            $item = $this->catalog->requireActive($command->equipmentUuid);
            $requirement = $this->em->getRepository(EquipmentRequirement::class)->findOneBy(['event' => $event, 'equipmentUuid' => $command->equipmentUuid]);
            $before = $requirement?->toArray();
            if ($requirement) {
                $requirement->changeQuantity($command->quantity, $command->notes);
            } else {
                $requirement = new EquipmentRequirement($event, $item['uuid'], $item['name'], $item['code'], $item['unit'], $command->quantity, $command->notes);
                $this->em->persist($requirement);
            }
            $this->preparation->equipmentChanged($event, $requirement, $before);
            return $requirement->toArray();
        });
    }
}
