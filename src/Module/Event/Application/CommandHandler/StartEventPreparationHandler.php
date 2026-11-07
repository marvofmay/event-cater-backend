<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\StartEventPreparation;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\EventFanOutNotifier;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class StartEventPreparationHandler
{
    public function __construct(
        private EventEditor $editor,
        private PreparationPlanner $planner,
        private EventFanOutNotifier $notifier,
    ) {
    }

    public function __invoke(StartEventPreparation $command): void
    {
        $status = '';
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_started', function (CateringEvent $event) use (&$status): array {
            $list = $this->planner->get($event);
            $list->start();
            $checkpoint = $this->planner->checkpoint($list);
            $status = (string) ($checkpoint['status'] ?? 'in_preparation');

            return $checkpoint;
        });
        $this->notifier->preparationStatusChanged($command->uuid, $status);
    }
}
