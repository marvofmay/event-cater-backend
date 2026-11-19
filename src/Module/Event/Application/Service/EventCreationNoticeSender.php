<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Domain\Entity\CateringEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Jednorazowe powiadomienie „Utworzenie wydarzenia” po uzupełnieniu obsady.
 * Drugie wywołanie nie wysyła nic ponownie.
 */
final readonly class EventCreationNoticeSender
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventNotificationAudienceResolver $audience,
        private EventFanOutNotifier $notifier,
        private EmployeeDirectory $employees,
    ) {
    }

    /**
     * @param list<string> $extraEmployeeUuids
     *
     * @return array{sent: bool, alreadySent: bool, sentAt: ?string, recipientCount: int, version: int}
     */
    public function send(string $eventUuid, array $extraEmployeeUuids): array
    {
        $extraEmployeeUuids = array_values(array_unique(array_filter(
            $extraEmployeeUuids,
            static fn (string $uuid): bool => $uuid !== '',
        )));

        return $this->em->wrapInTransaction(function () use ($eventUuid, $extraEmployeeUuids): array {
            $event = $this->em->find(CateringEvent::class, $eventUuid);
            if (!$event instanceof CateringEvent) {
                throw new PlanningResourceNotFound('Event not found.');
            }
            $this->em->lock($event, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($event);

            if ($event->creationNoticeSentAt() !== null) {
                return $this->payload($event, false, true, 0);
            }

            foreach ($extraEmployeeUuids as $employeeUuid) {
                $this->employees->requireActive($employeeUuid);
            }
            $this->audience->addExtras($event, $extraEmployeeUuids);

            $recipients = $this->audience->resolveUsers($event, [
                EventNotificationAudienceResolver::SOURCE_COORDINATOR,
                EventNotificationAudienceResolver::SOURCE_STAFF,
                EventNotificationAudienceResolver::SOURCE_PACKER,
                EventNotificationAudienceResolver::SOURCE_KITCHEN,
            ]);
            if ($recipients === []) {
                throw new BusinessRuleViolation('Nie ma jeszcze odbiorców powiadomienia. Wyznacz koordynatora, obsadę albo osobę kompletującą, albo wskaż dodatkową osobę.');
            }

            $event->markCreationNoticeSent(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->em->flush();
            $this->notifier->eventCreated($event->uuid());

            return $this->payload($event, true, false, count($recipients));
        });
    }

    /**
     * @return array{sent: bool, alreadySent: bool, sentAt: ?string, recipientCount: int, version: int}
     */
    private function payload(CateringEvent $event, bool $sent, bool $alreadySent, int $recipientCount): array
    {
        return [
            'sent' => $sent,
            'alreadySent' => $alreadySent,
            'sentAt' => $event->creationNoticeSentAt()?->format(DATE_ATOM),
            'recipientCount' => $recipientCount,
            'version' => $event->version(),
        ];
    }
}
