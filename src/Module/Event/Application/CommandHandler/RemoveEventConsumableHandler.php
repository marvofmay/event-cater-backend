<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Command\RemoveEventConsumable;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveEventConsumableHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $planner, private EntityManagerInterface $em)
    {
    }
    public function __invoke(RemoveEventConsumable $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'preparation_consumable_removed', function (CateringEvent $event) use ($command): array {
            $list = $this->planner->get($event);
            $line = $list->find('consumable', $command->itemUuid);
            if (!$line) {
                throw new PlanningResourceNotFound('Consumable line not found for this event.');
            }
            $before = $line->toArray();
            $list->remove($line);
            $this->em->remove($line);
            return [...$this->planner->checkpoint($list), 'removedLine' => $before];
        });
    }
}
