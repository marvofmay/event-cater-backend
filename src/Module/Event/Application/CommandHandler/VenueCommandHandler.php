<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Command\ApplyEventVenue;
use App\Module\Event\Application\Command\ChangeEventVenueActive;
use App\Module\Event\Application\Command\CreateVenueFromEvent;
use App\Module\Event\Application\Command\SaveEventVenue;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Application\Service\PreparationPlanner;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventVenue;
use App\Module\Event\Domain\Repository\EventRepository;
use App\Module\Event\Domain\Repository\VenueRepository;
use App\Module\Event\Domain\ValueObject\EventContact;
use App\Module\Event\Domain\ValueObject\EventLocation;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class VenueCommandHandler
{
    public function __construct(private VenueRepository $venues, private EventRepository $events, private EventEditor $editor, private PreparationPlanner $preparation)
    {
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveEventVenue $c): void
    {
        if ($c->version === null) {
            $this->venues->add(new EventVenue($c->uuid, $c->name, $c->location, $c->contacts, $c->notes, $c->serviceMode));
        } else {
            $this->venues->get($c->uuid, $c->version, true)->replace($c->name, $c->location, $c->contacts, $c->notes, $c->serviceMode);
        }
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function active(ChangeEventVenueActive $c): void
    {
        $this->venues->get($c->uuid, $c->version, true)->changeActive($c->active);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function fromEvent(CreateVenueFromEvent $c): void
    {
        $event = $this->events->getForUpdate($c->uuid, $c->version)->toArray();
        $this->venues->add(new EventVenue($c->venueUuid, $c->name, new EventLocation(...$event['location']), array_map(static fn (array $contact) => new EventContact(...$contact), $event['contacts']), $c->notes, $c->serviceMode));
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function apply(ApplyEventVenue $c): void
    {
        $this->editor->edit($c->uuid, $c->version, $c->actorUuid, 'venue_applied', function (CateringEvent $event) use ($c): array {
            $venue = $this->venues->get($c->venueUuid, $c->venueVersion);
            if (!$venue->active()) {
                throw new BusinessRuleViolation('Archived venue cannot be applied.');
            }
            $before = $event->toArray();
            $event->replaceDetails($before['title'], $before['description'], $event->schedule(), $venue->location(), $before['guestCount'], $before['coordinatorUuid'], $before['customerName']);
            if ($c->replaceContacts) {
                $event->replaceContacts($venue->contacts());
            }
            if ($before['location'] != $event->toArray()['location']) {
                $this->preparation->get($event)->invalidatePlan();
            }
            return ['venueUuid' => $c->venueUuid, 'venueVersion' => $c->venueVersion, 'beforeLocation' => $before['location'], 'afterLocation' => $event->toArray()['location'], 'contactsReplaced' => $c->replaceContacts];
        });
    }
}
