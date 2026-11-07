<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Command\AmendEventPreparation;
use App\Module\Event\Application\Port\ConsumableCatalog;
use App\Module\Event\Application\Port\EquipmentCatalog;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use App\Module\Event\Domain\Entity\PreparationLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AmendEventPreparationHandler
{
    public function __construct(
        private EventEditor $editor,
        private PreparationPlanner $planner,
        private ConsumableCatalog $consumables,
        private EquipmentCatalog $equipment,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AmendEventPreparation $command): void
    {
        if (!in_array($command->action, ['add', 'remove', 'set_required'], true)) {
            throw new BusinessRuleViolation('Unsupported preparation amendment action.');
        }
        if (!in_array($command->kind, ['consumable', 'equipment'], true)) {
            throw new BusinessRuleViolation('Unsupported preparation amendment kind.');
        }

        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_amended', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $list->assertExecutionAmendment($command->comment);

            return match ($command->kind) {
                'consumable' => $this->amendConsumable($list, $command),
                'equipment' => $this->amendEquipment($event, $list, $command),
            };
        });
    }

    private function amendConsumable(\App\Module\Event\Domain\Entity\EventPreparationList $list, AmendEventPreparation $command): array
    {
        if ($command->action === 'add' || $command->action === 'set_required') {
            if ($command->requiredQuantity === null) {
                throw new BusinessRuleViolation('requiredQuantity is required for this amendment.');
            }
            $snapshot = $this->consumables->requireActive($command->itemUuid);
            $line = $list->find('consumable', $command->itemUuid);
            if ($command->action === 'add') {
                if ($line !== null) {
                    throw new BusinessRuleViolation('This consumable is already on the preparation list.');
                }
                $line = PreparationLine::consumable($list, $snapshot, $command->requiredQuantity);
                $list->add($line, true);

                return [...$this->planner->checkpoint($list), 'amendment' => 'add', 'comment' => $command->comment, 'line' => $line->toArray()];
            }
            if ($line === null) {
                throw new PlanningResourceNotFound('Consumable line not found for this event.');
            }
            $before = $line->toArray();
            $list->amendRequiredQuantity($line, $command->requiredQuantity, $command->comment);

            return [...$this->planner->checkpoint($list), 'amendment' => 'set_required', 'comment' => $command->comment, 'before' => $before, 'after' => $line->toArray()];
        }

        $line = $list->find('consumable', $command->itemUuid);
        if ($line === null) {
            throw new PlanningResourceNotFound('Consumable line not found for this event.');
        }
        $before = $line->toArray();
        $list->remove($line, true);
        $this->em->remove($line);

        return [...$this->planner->checkpoint($list), 'amendment' => 'remove', 'comment' => $command->comment, 'removedLine' => $before];
    }

    private function amendEquipment(
        CateringEvent $event,
        \App\Module\Event\Domain\Entity\EventPreparationList $list,
        AmendEventPreparation $command,
    ): array {
        if ($command->action === 'add' || $command->action === 'set_required') {
            $quantity = $command->equipmentQuantity;
            if ($quantity === null || $quantity < 1) {
                throw new BusinessRuleViolation('equipmentQuantity must be a positive integer.');
            }
            $item = $this->equipment->requireActive($command->itemUuid);
            $requirement = $this->em->getRepository(EquipmentRequirement::class)->findOneBy([
                'event' => $event,
                'equipmentUuid' => $command->itemUuid,
            ]);
            $before = $requirement?->toArray();
            if ($command->action === 'add' && $requirement !== null) {
                throw new BusinessRuleViolation('This equipment is already on the preparation list.');
            }
            if ($requirement) {
                $requirement->changeQuantity($quantity, $requirement->toArray()['notes'] ?? null);
            } else {
                $requirement = new EquipmentRequirement(
                    $event,
                    $item['uuid'],
                    $item['name'],
                    $item['code'],
                    $item['unit'],
                    $quantity,
                    null,
                );
                $this->em->persist($requirement);
            }
            $line = $list->find('equipment', $command->itemUuid);
            if (!$line) {
                $line = PreparationLine::equipment($list, $requirement);
                $line->configure(null, null, $item['storageLocation'] ?? null, null);
                $list->add($line, true);
            } else {
                $list->markExecutionAmended();
            }

            return [
                ...$this->planner->checkpoint($list),
                'amendment' => $command->action,
                'comment' => $command->comment,
                'before' => $before,
                'after' => $requirement->toArray(),
                'line' => $line->toArray(),
            ];
        }

        $requirement = $this->em->getRepository(EquipmentRequirement::class)->findOneBy([
            'event' => $event,
            'equipmentUuid' => $command->itemUuid,
        ]);
        if (!$requirement) {
            throw new PlanningResourceNotFound('Equipment requirement not found for this event.');
        }
        $before = $requirement->toArray();
        $this->planner->equipmentRemovedAmendment($event, $requirement);
        $this->em->remove($requirement);

        return [...$this->planner->checkpoint($list), 'amendment' => 'remove', 'comment' => $command->comment, 'removed' => $before];
    }
}
