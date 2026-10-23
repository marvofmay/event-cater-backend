<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ApproveEventPreparation;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApproveEventPreparationHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner)
    {
    }
    public function __invoke(ApproveEventPreparation $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_approved', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $this->planner->validateResponsibilities($list);
            $list->approve($command->actorUuid);
            return $this->planner->checkpoint($list);
        });
    }
}
