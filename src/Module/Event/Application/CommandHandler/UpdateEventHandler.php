<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\UpdateEvent;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateEventHandler
{
    public function __construct(private EventEditor $editor, private PreparationPlanner $preparation, private EmployeeDirectory $employees)
    {
    }
    public function __invoke(UpdateEvent $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'details_replaced', function (CateringEvent $event) use ($command): void {
            $before = $event->toArray();
            $d = $command->details;
            if ($d->coordinatorUuid) {
                $this->employees->requireActive($d->coordinatorUuid);
            }
            $event->replaceDetails($d->title, $d->description, $d->schedule, $d->location, $d->guestCount, $d->coordinatorUuid, $d->customerName);
            $this->preparation->contextChanged($event, $before);
        });
    }
}
