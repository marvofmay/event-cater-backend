<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ChangeEventStatus;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Application\Service\StaffPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Enum\EventStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeEventStatusHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $preparation, private StaffPlanner $staff)
    {
    }
    public function __invoke(ChangeEventStatus $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'status_changed', function (CateringEvent $event) use ($command): array {
            if ($command->status === EventStatus::CONFIRMED) {
                $this->staff->validateTeam($event);
            }
            if ($command->status === EventStatus::IN_PROGRESS) {
                $this->preparation->assertDispatched($event);
            }
            $previous = $event->status()->value;
            $event->transitionTo($command->status);
            return ['from' => $previous, 'to' => $command->status->value];
        });
    }
}
