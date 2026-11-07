<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ConfirmPreparationLoaded;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\EventFanOutNotifier;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfirmPreparationLoadedHandler
{
    public function __construct(
        private EventEditor $editor,
        private PreparationPlanner $planner,
        private EventFanOutNotifier $notifier,
    ) {
    }

    public function __invoke(ConfirmPreparationLoaded $command): void
    {
        $status = '';
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_load_confirmed', function (CateringEvent $event) use ($command, &$status): array {
            $list = $this->planner->get($event);
            $this->planner->validateResponsibilities($list);
            $list->confirmLoaded($command->actorUuid, $command->acceptShortagesReason);
            $checkpoint = $this->planner->checkpoint($list);
            $status = (string) ($checkpoint['status'] ?? 'loaded');

            return $checkpoint;
        });
        $this->notifier->preparationStatusChanged($command->uuid, $status);
    }
}
