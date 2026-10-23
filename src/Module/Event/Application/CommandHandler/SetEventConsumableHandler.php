<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\SetEventConsumable;
use App\Module\Event\Application\Port\ConsumableCatalog;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\PreparationLine;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetEventConsumableHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner, private ConsumableCatalog $catalog)
    {
    }
    public function __invoke(SetEventConsumable $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_consumable_set', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $snapshot = $this->catalog->requireActive($command->itemUuid);
            $line = $list->find('consumable', $command->itemUuid);
            if ($line === null) {
                $line = PreparationLine::consumable($list, $snapshot, $command->quantity);
                $list->add($line);
            } else {
                $line->setRequiredQuantity($command->quantity);
                $line->useRule(null);
                $list->invalidatePlan();
            }
            return [...$this->planner->checkpoint($list), 'line' => $line->toArray()];
        });
    }
}
