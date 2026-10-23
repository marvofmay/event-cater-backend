<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Command\RecordPreparationProgress;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RecordPreparationProgressHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner)
    {
    }
    public function __invoke(RecordPreparationProgress $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_progress_recorded', function (CateringEvent $event) use ($command): array {
            if ($command->prepared === null && $command->loaded === null) {
                throw new BusinessRuleViolation('Supply preparedQuantity or loadedQuantity.');
            }
            $list = $this->planner->get($event);
            $line = $this->planner->requireLine($list, $command->lineUuid);
            $before = $line->toArray();
            if ($line->recordProgress($command->prepared, $command->loaded, $command->actorUuid, $command->correctionReason, $list->isApproved())) {
                $list->afterProgressChanged();
            }
            return [...$this->planner->checkpoint($list), 'before' => $before, 'after' => $line->toArray(), 'correctionReason' => $command->correctionReason];
        });
    }
}
