<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ReplaceEventContacts;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Domain\Entity\CateringEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ReplaceEventContactsHandler
{
    public function __construct(private EventEditor $editor)
    {
    }
    public function __invoke(ReplaceEventContacts $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'contacts_replaced', function (CateringEvent $event) use ($command): void {
            $event->replaceContacts($command->contacts);
        });
    }
}
