<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ConfigurePreparationLine;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfigurePreparationLineHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner, private EmployeeDirectory $employees)
    {
    }
    public function __invoke(ConfigurePreparationLine $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_line_configured', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $line = $this->planner->requireLine($list, $command->lineUuid);
            $name = $command->responsibleUuid === null ? null : $this->employees->requireActive($command->responsibleUuid);
            $line->configure($command->responsibleUuid, $name, $command->sourceLocation, $command->instructions);
            $list->invalidatePlan();
            return [...$this->planner->checkpoint($list), 'line' => $line->toArray()];
        });
    }
}
