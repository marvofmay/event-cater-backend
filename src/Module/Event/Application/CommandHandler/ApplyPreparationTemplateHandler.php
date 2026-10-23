<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Command\ApplyPreparationTemplate;
use App\Module\Event\Application\Port\ConsumableCatalog;
use App\Module\Event\Application\Port\EquipmentCatalog;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use App\Module\Event\Domain\Entity\PreparationLine;
use App\Module\Event\Domain\Repository\PreparationTemplateRepository;
use App\Module\Event\Domain\ValueObject\PreparationRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyPreparationTemplateHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner, private PreparationTemplateRepository $templates, private EquipmentCatalog $equipment, private ConsumableCatalog $consumables, private EntityManagerInterface $em)
    {
    }

    public function __invoke(ApplyPreparationTemplate $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_template_applied', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $template = $this->templates->getForApply($command->templateUuid, $command->templateVersion);
            if (!$template->isActive()) {
                throw new BusinessRuleViolation('Template must be active.');
            }
            $applied = [];
            $definitions = $template->lines();
            usort($definitions, static fn (array $a, array $b) => [$a['kind'], $a['itemUuid']] <=> [$b['kind'], $b['itemUuid']]);
            foreach ($definitions as $definition) {
                $snapshot = $definition['kind'] === 'equipment' ? $this->equipment->requireActive($definition['itemUuid']) : $this->consumables->requireActive($definition['itemUuid']);
                if ($snapshot['unit'] !== $definition['unit']) {
                    throw new BusinessRuleViolation('Catalog unit changed. Update the template before applying it.');
                }
                $rule = PreparationRule::restore($definition['rule']);
                $quantity = $rule->calculate($event->toArray()['guestCount'], $snapshot['unit']);
                $line = $list->find($definition['kind'], $definition['itemUuid']);
                if ($definition['kind'] === 'equipment') {
                    $requirement = $line?->equipmentRequirement();
                    if ($requirement === null) {
                        $requirement = new EquipmentRequirement($event, $snapshot['uuid'], $snapshot['name'], $snapshot['code'], $snapshot['unit'], intdiv($quantity->milliunits(), 1000), null);
                        $this->em->persist($requirement);
                        $line = PreparationLine::equipment($list, $requirement);
                        $list->add($line);
                    } else {
                        $requirement->changeQuantity(intdiv($quantity->milliunits(), 1000), $requirement->toArray()['notes']);
                    }
                } elseif ($line === null) {
                    $line = PreparationLine::consumable($list, $snapshot, $quantity);
                    $list->add($line);
                } else {
                    $line->setRequiredQuantity($quantity);
                }
                // Existing snapshot units are immutable; an archived/recreated catalog item has a different UUID.
                if ($line->unit() !== $snapshot['unit']) {
                    throw new BusinessRuleViolation('Catalog unit differs from the event snapshot.');
                }
                $current = $line->toArray();
                $line->configure($line->responsibleUuid(), $current['responsibleName'], $definition['sourceLocation'], $definition['instructions']);
                $line->useRule($rule, $command->templateUuid, $command->templateVersion);
                $applied[] = $line->toArray();
            }
            $list->invalidatePlan();
            return [...$this->planner->checkpoint($list), 'sourceTemplateUuid' => $command->templateUuid, 'sourceTemplateVersion' => $command->templateVersion, 'appliedLines' => $applied];
        });
    }
}
