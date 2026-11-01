<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ConfigurePreparationLine;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfigurePreparationLineHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner)
    {
    }
    public function __invoke(ConfigurePreparationLine $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_line_configured', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $line = $this->planner->requireLine($list, $command->lineUuid);
            $before = $line->toArray();
            if ($command->responsibleUuid !== null) throw new BusinessRuleViolation('Wybierz jedną osobę do kompletowania całej listy, nie poszczególnych pozycji.');
            if ($command->requiredQuantity !== null) {
                $command->requiredQuantity->assertUnit($line->unit());
                if ($line->equipmentRequirement() !== null) {
                    $requirement = $line->equipmentRequirement();
                    $requirement->changeQuantity(intdiv($command->requiredQuantity->milliunits(), 1000), $requirement->toArray()['notes']);
                } else {
                    $line->setRequiredQuantity($command->requiredQuantity);
                }
                $line->useRule(null);
            }
            $line->configure(null, null, $command->sourceLocation, $command->instructions);
            $list->invalidatePlan();
            return [...$this->planner->checkpoint($list), 'line' => $line->toArray(), 'before' => $before, 'after' => $line->toArray()];
        });
    }
}
