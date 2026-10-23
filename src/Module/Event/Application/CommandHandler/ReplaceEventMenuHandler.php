<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ReplaceEventMenu;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ReplaceEventMenuHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $preparation)
    {
    }
    public function __invoke(ReplaceEventMenu $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'menu_replaced', function (CateringEvent $event) use ($command): void {
            $before = $event->toArray()['menu'];
            $event->replaceMenu($command->items);
            if ($before !== $event->toArray()['menu']) {
                $this->preparation->get($event)->invalidatePlan();
            }
        });
    }
}
