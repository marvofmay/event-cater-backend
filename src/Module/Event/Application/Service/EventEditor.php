<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Module\Event\Domain\Entity\EventHistoryEntry;
use App\Module\Event\Domain\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventEditor
{
    public function __construct(private EventRepository $events, private EntityManagerInterface $em)
    {
    }

    public function edit(string $uuid, int $version, string $actorUuid, string $action, callable $change, bool $media = false): void
    {
        $event = $this->events->getForUpdate($uuid, $version);
        if ($media) {
            if ($event->status() === \App\Module\Event\Domain\Enum\EventStatus::CANCELLED) {
                throw new \App\Common\Domain\Exception\BusinessRuleViolation('Cancelled events are read-only.');
            }
        } else {
            $event->assertEditable();
        }
        $details = $change($event);
        $event->touch();
        $details ??= $event->toArray();
        if (array_key_exists('version', $details)) {
            $details['version'] = $event->version() + 1;
        }
        $this->em->persist(new EventHistoryEntry($event, $actorUuid, $action, $details, $event->version() + 1));
    }
}
