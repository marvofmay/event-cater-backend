<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Module\Company\Domain\Entity\User;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventNotificationRecipientOverride;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Entity\StaffAssignment;
use App\Module\Event\Domain\Enum\EventFunction;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Buduje listę odbiorców fan-outu: (default − excluded) ∪ extra → User[].
 */
final readonly class EventNotificationAudienceResolver
{
    public const SOURCE_STAFF = 'staff';
    public const SOURCE_COORDINATOR = 'coordinator';
    public const SOURCE_PACKER = 'packer';
    public const SOURCE_KITCHEN = 'kitchen';
    /** Kierowca / logistyka — odbiorcy po złożeniu listy (załadunek). */
    public const SOURCE_LOADING = 'loading';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param list<string> $includeSources
     *
     * @return list<User>
     */
    public function resolveUsers(CateringEvent $event, array $includeSources): array
    {
        $employeeUuids = $this->resolveEmployeeUuids($event, $includeSources);

        return $this->usersForEmployees($employeeUuids);
    }

    /**
     * @param list<string> $includeSources
     *
     * @return list<string>
     */
    public function resolveEmployeeUuids(CateringEvent $event, array $includeSources): array
    {
        $defaults = $this->defaultAudience($event, $includeSources);
        $overrides = $this->overrides($event->uuid());
        $excluded = $overrides[EventNotificationRecipientOverride::KIND_EXCLUDED] ?? [];
        $extra = $overrides[EventNotificationRecipientOverride::KIND_EXTRA] ?? [];

        $result = [];
        foreach (array_keys($defaults) as $employeeUuid) {
            if (!isset($excluded[$employeeUuid])) {
                $result[$employeeUuid] = true;
            }
        }
        foreach (array_keys($extra) as $employeeUuid) {
            $result[$employeeUuid] = true;
        }

        return array_keys($result);
    }

    /**
     * @return array{
     *   defaults: list<array{employeeUuid: string, name: string, sources: list<string>, excluded: bool}>,
     *   extra: list<array{employeeUuid: string, name: string}>,
     *   effective: list<array{employeeUuid: string, name: string}>,
     *   channels: array{internal: bool, email: bool, sms: bool}|null,
     *   channelsInherited: bool
     * }
     */
    public function describe(CateringEvent $event): array
    {
        $sources = [
            self::SOURCE_COORDINATOR,
            self::SOURCE_STAFF,
            self::SOURCE_PACKER,
            self::SOURCE_KITCHEN,
            self::SOURCE_LOADING,
        ];
        $defaults = $this->defaultAudience($event, $sources);
        $overrides = $this->overrides($event->uuid());
        $excluded = $overrides[EventNotificationRecipientOverride::KIND_EXCLUDED] ?? [];
        $extra = $overrides[EventNotificationRecipientOverride::KIND_EXTRA] ?? [];
        $names = $this->employeeNames(array_unique([...array_keys($defaults), ...array_keys($extra)]));

        $defaultRows = [];
        foreach ($defaults as $employeeUuid => $sourceList) {
            $defaultRows[] = [
                'employeeUuid' => $employeeUuid,
                'name' => $names[$employeeUuid] ?? $employeeUuid,
                'sources' => array_values($sourceList),
                'excluded' => isset($excluded[$employeeUuid]),
            ];
        }
        usort($defaultRows, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $extraRows = [];
        foreach (array_keys($extra) as $employeeUuid) {
            if (isset($defaults[$employeeUuid])) {
                continue;
            }
            $extraRows[] = [
                'employeeUuid' => $employeeUuid,
                'name' => $names[$employeeUuid] ?? $employeeUuid,
            ];
        }
        usort($extraRows, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $effectiveUuids = $this->resolveEmployeeUuids($event, $sources);
        $effective = [];
        foreach ($effectiveUuids as $employeeUuid) {
            $effective[] = [
                'employeeUuid' => $employeeUuid,
                'name' => $names[$employeeUuid] ?? $employeeUuid,
            ];
        }
        usort($effective, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $channels = $event->notificationChannels();

        return [
            'defaults' => $defaultRows,
            'extra' => $extraRows,
            'effective' => $effective,
            'channels' => $channels,
            'channelsInherited' => $channels === null,
        ];
    }

    /**
     * @param list<string>             $excludedEmployeeUuids
     * @param list<string>             $extraEmployeeUuids
     * @param array<string, bool>|null $channels null = dziedzicz ustawienia firmowe typu zdarzenia
     */
    public function replaceOverrides(CateringEvent $event, array $excludedEmployeeUuids, array $extraEmployeeUuids, ?array $channels = null): void
    {
        $excluded = array_values(array_unique(array_filter($excludedEmployeeUuids)));
        $extra = array_values(array_unique(array_filter($extraEmployeeUuids)));
        $overlap = array_intersect($excluded, $extra);
        if ($overlap !== []) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Ta sama osoba nie może być jednocześnie wykluczona i dodana jako dodatkowy odbiorca.');
        }

        $existing = $this->em->getRepository(EventNotificationRecipientOverride::class)->findBy(['event' => $event]);
        foreach ($existing as $row) {
            $this->em->remove($row);
        }
        $this->em->flush();

        foreach ($excluded as $employeeUuid) {
            $this->em->persist(new EventNotificationRecipientOverride($event, $employeeUuid, EventNotificationRecipientOverride::KIND_EXCLUDED));
        }
        foreach ($extra as $employeeUuid) {
            $this->em->persist(new EventNotificationRecipientOverride($event, $employeeUuid, EventNotificationRecipientOverride::KIND_EXTRA));
        }
        $event->replaceNotificationChannels($channels);
        $this->em->flush();
    }

    /**
     * @param list<string> $includeSources
     *
     * @return array<string, list<string>>
     */
    private function defaultAudience(CateringEvent $event, array $includeSources): array
    {
        $audience = [];
        $add = static function (array &$audience, ?string $employeeUuid, string $source) use ($includeSources): void {
            if ($employeeUuid === null || $employeeUuid === '' || !in_array($source, $includeSources, true)) {
                return;
            }
            $audience[$employeeUuid] ??= [];
            if (!in_array($source, $audience[$employeeUuid], true)) {
                $audience[$employeeUuid][] = $source;
            }
        };

        $add($audience, $event->coordinatorUuid(), self::SOURCE_COORDINATOR);

        if (in_array(self::SOURCE_STAFF, $includeSources, true)) {
            foreach ($this->em->getRepository(StaffAssignment::class)->findBy(['event' => $event]) as $assignment) {
                $add($audience, $assignment->employeeUuid(), self::SOURCE_STAFF);
            }
        }

        if (in_array(self::SOURCE_PACKER, $includeSources, true)) {
            $list = $this->em->getRepository(EventPreparationList::class)->findOneBy(['event' => $event]);
            if ($list instanceof EventPreparationList) {
                $add($audience, $list->responsibleUuid(), self::SOURCE_PACKER);
            }
        }

        if (in_array(self::SOURCE_KITCHEN, $includeSources, true)) {
            $order = $this->em->getRepository(KitchenOrder::class)->findOneBy(['eventUuid' => $event->uuid()], ['createdAt' => 'DESC']);
            if ($order instanceof KitchenOrder) {
                $plan = $order->plan();
                $add($audience, $plan['responsibleUuid'] ?? null, self::SOURCE_KITCHEN);
            }
        }

        if (in_array(self::SOURCE_LOADING, $includeSources, true)) {
            $loadingFunctions = [
                EventFunction::DRIVER->value,
                EventFunction::LOGISTICS->value,
            ];
            foreach ($this->em->getRepository(StaffAssignment::class)->findBy(['event' => $event]) as $assignment) {
                if (!$assignment instanceof StaffAssignment) {
                    continue;
                }
                $functions = $assignment->toArray()['functions'] ?? [];
                if (!is_array($functions) || array_intersect($loadingFunctions, $functions) === []) {
                    continue;
                }
                $add($audience, $assignment->employeeUuid(), self::SOURCE_LOADING);
            }
        }

        return $audience;
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function overrides(string $eventUuid): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('o')
            ->from(EventNotificationRecipientOverride::class, 'o')
            ->join('o.event', 'e')
            ->where('e.uuid = :eventUuid')
            ->setParameter('eventUuid', $eventUuid)
            ->getQuery()
            ->getResult();

        $grouped = [
            EventNotificationRecipientOverride::KIND_EXTRA => [],
            EventNotificationRecipientOverride::KIND_EXCLUDED => [],
        ];
        foreach ($rows as $row) {
            if (!$row instanceof EventNotificationRecipientOverride) {
                continue;
            }
            $grouped[$row->kind()][$row->employeeUuid()] = true;
        }

        return $grouped;
    }

    /**
     * @param list<string> $employeeUuids
     *
     * @return list<User>
     */
    private function usersForEmployees(array $employeeUuids): array
    {
        if ($employeeUuids === []) {
            return [];
        }
        /** @var list<User> $users */
        $users = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('IDENTITY(u.employee) IN (:uuids)')
            ->setParameter('uuids', $employeeUuids)
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @param list<string> $employeeUuids
     *
     * @return array<string, string>
     */
    private function employeeNames(array $employeeUuids): array
    {
        if ($employeeUuids === []) {
            return [];
        }
        /** @var list<\App\Module\Company\Domain\Entity\Employee> $employees */
        $employees = $this->em->createQueryBuilder()
            ->select('e')
            ->from(\App\Module\Company\Domain\Entity\Employee::class, 'e')
            ->where('e.uuid IN (:uuids)')
            ->setParameter('uuids', $employeeUuids)
            ->getQuery()
            ->getResult();

        $names = [];
        foreach ($employees as $employee) {
            $names[(string) $employee->getUUID()] = trim($employee->getFirstName().' '.$employee->getLastName());
        }

        // Prefer staff assignment display names when available.
        foreach ($this->em->getRepository(StaffAssignment::class)->findBy(['employeeUuid' => $employeeUuids]) as $assignment) {
            if ($assignment instanceof StaffAssignment && $assignment->employeeName() !== '') {
                $names[$assignment->employeeUuid()] = $assignment->employeeName();
            }
        }

        return $names;
    }
}
