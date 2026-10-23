<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ConfigureEventPreparation;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfigureEventPreparationHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner, private EmployeeDirectory $employees)
    {
    }
    public function __invoke(ConfigureEventPreparation $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_configured', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $name = $command->responsibleUuid === null ? null : $this->employees->requireActive($command->responsibleUuid);
            $list->configure($command->responsibleUuid, $name, $command->deadlineAt, $command->notes);
            return $this->planner->checkpoint($list);
        });
    }
}
