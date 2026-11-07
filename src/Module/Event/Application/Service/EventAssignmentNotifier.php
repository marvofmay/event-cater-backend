<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Module\Company\Domain\Entity\User;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\System\Notification\Domain\Interface\Message\NotificationResolveInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Powiadamia pracownika o obsadzeniu / usunięciu z obsady albo o wyznaczeniu do kompletacji / kuchni.
 */
final readonly class EventAssignmentNotifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationResolveInterface $notifications,
        private LoggerInterface $logger,
    ) {
    }

    public function staffAssigned(string $employeeUuid, string $eventTitle, string $eventDate, array $functionLabels, ?string $eventUuid = null): void
    {
        $functions = $functionLabels === [] ? '' : ' Twoje funkcje: '.implode(', ', $functionLabels).'.';
        $this->notify('EventStaffAssigned', $employeeUuid, [
            'eventTitle' => $eventTitle,
            'eventDate' => $eventDate,
            'functions' => $functions,
        ], $eventUuid);
    }

    public function staffRemoved(string $employeeUuid, string $eventTitle, string $eventDate, ?string $eventUuid = null): void
    {
        $this->notify('EventStaffRemoved', $employeeUuid, [
            'eventTitle' => $eventTitle,
            'eventDate' => $eventDate,
        ], $eventUuid);
    }

    public function packerAssigned(string $employeeUuid, string $eventTitle, string $eventDate, bool $alsoOnStaff, ?string $eventUuid = null): void
    {
        $staffNote = $alsoOnStaff
            ? ' Jesteś też w obsadzie tego wydarzenia.'
            : ' Nie jesteś w obsadzie wydarzenia - masz dostęp do listy kompletacji.';
        $this->notify('EventPackerAssigned', $employeeUuid, [
            'eventTitle' => $eventTitle,
            'eventDate' => $eventDate,
            'staffNote' => $staffNote,
        ], $eventUuid);
    }

    public function kitchenResponsibleAssigned(string $employeeUuid, string $eventTitle, string $dueAt, ?string $eventUuid = null): void
    {
        $this->notify('KitchenResponsibleAssigned', $employeeUuid, [
            'eventTitle' => $eventTitle,
            'dueAt' => $dueAt !== '' ? $dueAt : '—',
        ], $eventUuid);
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private function notify(string $eventName, string $employeeUuid, array $payload, ?string $eventUuid = null): void
    {
        try {
            $user = $this->userForEmployee($employeeUuid);
            if ($user === null) {
                return;
            }
            $this->notifications->publish($eventName, $payload, [$user], $this->channelsForEvent($eventUuid));
        } catch (\Throwable $e) {
            $this->logger->error('Event assignment notification failed', [
                'eventName' => $eventName,
                'employeeUuid' => $employeeUuid,
                'exception' => $e,
            ]);
        }
    }

    /**
     * @return list<string>|null
     */
    private function channelsForEvent(?string $eventUuid): ?array
    {
        if ($eventUuid === null || $eventUuid === '') {
            return null;
        }
        $event = $this->em->find(CateringEvent::class, $eventUuid);

        return $event instanceof CateringEvent ? $event->allowedNotificationChannels() : null;
    }

    private function userForEmployee(string $employeeUuid): ?User
    {
        $user = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('IDENTITY(u.employee) = :employeeUuid')
            ->setParameter('employeeUuid', $employeeUuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $user instanceof User ? $user : null;
    }
}
