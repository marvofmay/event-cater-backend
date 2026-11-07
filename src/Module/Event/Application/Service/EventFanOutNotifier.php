<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\System\Notification\Domain\Interface\Message\NotificationResolveInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class EventFanOutNotifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventNotificationAudienceResolver $audience,
        private NotificationResolveInterface $notifications,
        private LoggerInterface $logger,
    ) {
    }

    public function eventCreated(string $eventUuid): void
    {
        $this->safe($eventUuid, 'EventCreated', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_STAFF,
            EventNotificationAudienceResolver::SOURCE_PACKER,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event): array {
            $snapshot = $event->toArray();
            $location = is_array($snapshot['location'] ?? null)
                ? trim(implode(', ', array_filter([
                    $snapshot['location']['city'] ?? null,
                    $snapshot['location']['name'] ?? null,
                    $snapshot['location']['street'] ?? null,
                ])))
                : '';

            return [
                'eventTitle' => (string) $snapshot['title'],
                'eventDate' => (string) $snapshot['date'],
                'location' => $location !== '' ? $location : '—',
            ];
        });
    }

    /**
     * @param list<string> $changedFields
     */
    public function eventUpdated(string $eventUuid, array $changedFields): void
    {
        if ($changedFields === []) {
            return;
        }
        $this->safe($eventUuid, 'EventUpdated', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_STAFF,
            EventNotificationAudienceResolver::SOURCE_PACKER,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event) use ($changedFields): array {
            $snapshot = $event->toArray();
            $labels = [
                'date' => 'data',
                'timezone' => 'strefa',
                'readyAt' => 'gotowość',
                'startsAt' => 'start',
                'endsAt' => 'koniec',
                'location' => 'miejsce',
                'guestCount' => 'liczba gości',
                'coordinatorUuid' => 'koordynator',
            ];
            $named = array_map(static fn (string $field) => $labels[$field] ?? $field, $changedFields);

            return [
                'eventTitle' => (string) $snapshot['title'],
                'eventDate' => (string) $snapshot['date'],
                'changedFields' => implode(', ', $named),
            ];
        }, skipTerminal: true);
    }

    public function eventStatusChanged(string $eventUuid, string $status): void
    {
        $this->safe($eventUuid, 'EventStatusChanged', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_STAFF,
        ], static function (CateringEvent $event) use ($status): array {
            $snapshot = $event->toArray();
            $labels = [
                'draft' => 'szkic',
                'planned' => 'zaplanowane',
                'confirmed' => 'potwierdzone',
                'in_progress' => 'w trakcie',
                'completed' => 'zakończone',
                'cancelled' => 'anulowane',
            ];

            return [
                'eventTitle' => (string) $snapshot['title'],
                'eventDate' => (string) $snapshot['date'],
                'status' => $labels[$status] ?? $status,
            ];
        });
    }

    public function eventMenuChanged(string $eventUuid): void
    {
        $this->safe($eventUuid, 'EventMenuChanged', [
            EventNotificationAudienceResolver::SOURCE_STAFF,
            EventNotificationAudienceResolver::SOURCE_PACKER,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'eventDate' => (string) $snapshot['date'],
            ];
        }, skipTerminal: true);
    }

    public function preparationStatusChanged(string $eventUuid, string $preparationStatus): void
    {
        $this->safe($eventUuid, 'PreparationStatusChanged', [
            EventNotificationAudienceResolver::SOURCE_STAFF,
            EventNotificationAudienceResolver::SOURCE_PACKER,
        ], static function (CateringEvent $event) use ($preparationStatus): array {
            $snapshot = $event->toArray();
            $labels = [
                'draft' => 'szkic',
                'approved' => 'zatwierdzona',
                'in_preparation' => 'w kompletacji',
                'ready_for_loading' => 'gotowe do załadunku',
                'loaded' => 'załadowane',
            ];

            return [
                'eventTitle' => (string) $snapshot['title'],
                'preparationStatus' => $labels[$preparationStatus] ?? $preparationStatus,
            ];
        }, skipTerminal: true);
    }

    public function preparationPacked(string $eventUuid, ?string $comment = null): void
    {
        // Do koordynatora oraz kierowcy/logistyki — nie do kompletującego (packer).
        $excludePacker = [];
        $event = $this->em->find(CateringEvent::class, $eventUuid);
        if ($event instanceof CateringEvent) {
            $list = $this->em->getRepository(EventPreparationList::class)->findOneBy(['event' => $event]);
            if ($list instanceof EventPreparationList && $list->responsibleUuid()) {
                $excludePacker[] = $list->responsibleUuid();
            }
        }

        $this->safe($eventUuid, 'PreparationPacked', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_LOADING,
        ], static function (CateringEvent $event) use ($comment): array {
            $snapshot = $event->toArray();
            $note = trim((string) $comment);

            return [
                'eventTitle' => (string) $snapshot['title'],
                'eventDate' => (string) $snapshot['date'],
                'comment' => $note !== '' ? $note : '—',
            ];
        }, skipTerminal: true, excludeEmployeeUuids: $excludePacker);
    }

    public function kitchenTransition(string $eventUuid, string $action, string $kitchenStatus, ?string $responsibleUuid = null): void
    {
        $eventName = match ($action) {
            'release' => 'KitchenOrderReleased',
            'ready' => 'KitchenOrderReady',
            'hand-over' => 'KitchenOrderHandedOver',
            default => null,
        };
        if ($eventName === null) {
            return;
        }

        $sources = [
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ];
        if ($action === 'release') {
            $sources[] = EventNotificationAudienceResolver::SOURCE_COORDINATOR;
        }

        $this->safe($eventUuid, $eventName, $sources, static function (CateringEvent $event) use ($kitchenStatus): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'kitchenStatus' => $kitchenStatus,
            ];
        }, skipTerminal: true);
    }

    public function kitchenRevisionProposed(string $eventUuid, string $reason): void
    {
        $this->safe($eventUuid, 'KitchenRevisionProposed', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event) use ($reason): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'reason' => $reason !== '' ? $reason : '—',
            ];
        }, skipTerminal: true);
    }

    public function kitchenRevisionAccepted(string $eventUuid): void
    {
        $this->safe($eventUuid, 'KitchenRevisionAccepted', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event): array {
            $snapshot = $event->toArray();

            return ['eventTitle' => (string) $snapshot['title']];
        }, skipTerminal: true);
    }

    public function kitchenRevisionRejected(string $eventUuid, string $reason): void
    {
        $this->safe($eventUuid, 'KitchenRevisionRejected', [
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
        ], static function (CateringEvent $event) use ($reason): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'reason' => $reason !== '' ? $reason : '—',
            ];
        }, skipTerminal: true);
    }

    public function preparationDeadlineReminder(string $eventUuid, string $deadlineAt): void
    {
        $this->safe($eventUuid, 'PreparationDeadlineReminder', [
            EventNotificationAudienceResolver::SOURCE_PACKER,
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
        ], static function (CateringEvent $event) use ($deadlineAt): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'deadlineAt' => $deadlineAt,
            ];
        }, skipTerminal: true);
    }

    public function kitchenDueReminder(string $eventUuid, string $dueAt): void
    {
        $this->safe($eventUuid, 'KitchenDueReminder', [
            EventNotificationAudienceResolver::SOURCE_KITCHEN,
            EventNotificationAudienceResolver::SOURCE_COORDINATOR,
        ], static function (CateringEvent $event) use ($dueAt): array {
            $snapshot = $event->toArray();

            return [
                'eventTitle' => (string) $snapshot['title'],
                'dueAt' => $dueAt,
            ];
        }, skipTerminal: true);
    }

    /**
     * @param list<string>                     $sources
     * @param callable(CateringEvent): array   $payloadFactory
     * @param list<string>                     $excludeEmployeeUuids
     */
    private function safe(
        string $eventUuid,
        string $eventName,
        array $sources,
        callable $payloadFactory,
        bool $skipTerminal = false,
        array $excludeEmployeeUuids = [],
    ): void {
        try {
            $event = $this->em->find(CateringEvent::class, $eventUuid);
            if (!$event instanceof CateringEvent) {
                return;
            }
            if ($skipTerminal && in_array($event->status()->value, ['draft', 'cancelled', 'completed'], true)) {
                return;
            }
            $recipients = $this->audience->resolveUsers($event, $sources);
            if ($excludeEmployeeUuids !== []) {
                $excluded = array_fill_keys($excludeEmployeeUuids, true);
                $recipients = array_values(array_filter(
                    $recipients,
                    static fn ($user) => !isset($excluded[(string) $user->getEmployeeUUID()]),
                ));
            }
            if ($recipients === []) {
                return;
            }
            $this->notifications->publish(
                $eventName,
                $payloadFactory($event),
                $recipients,
                $event->allowedNotificationChannels(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Event fan-out notification failed', [
                'eventName' => $eventName,
                'eventUuid' => $eventUuid,
                'exception' => $e,
            ]);
        }
    }
}
