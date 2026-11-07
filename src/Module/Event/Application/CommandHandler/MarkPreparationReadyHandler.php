<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\MarkPreparationReady;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\EventFanOutNotifier;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class MarkPreparationReadyHandler
{
    public function __construct(
        private EventEditor $editor,
        private PreparationPlanner $planner,
        private EventFanOutNotifier $notifier,
    ) {
    }

    public function __invoke(MarkPreparationReady $command): void
    {
        $comment = null;
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_ready', function (CateringEvent $event) use ($command, &$comment): array {
            $list = $this->planner->get($event);
            $list->markReady($command->actorUuid, $command->comment);
            $checkpoint = $this->planner->checkpoint($list);
            $comment = $checkpoint['readyComment'] ?? null;

            return $checkpoint;
        });
        $this->notifier->preparationPacked(
            $command->uuid,
            is_string($comment) ? $comment : null,
        );
    }
}
