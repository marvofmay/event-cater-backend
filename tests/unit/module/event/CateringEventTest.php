<?php

declare(strict_types=1);

namespace App\tests\unit\module\event;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Enum\EventStatus;
use App\Module\Event\Domain\ValueObject\EventContact;
use App\Module\Event\Domain\ValueObject\EventLocation;
use App\Module\Event\Domain\ValueObject\EventSchedule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CateringEventTest extends TestCase
{
    public function testDraftRequiresOnlyTitleAndDate(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Spotkanie', new EventSchedule('2026-12-31'));
        self::assertSame('draft', $event->toArray()['status']);
        self::assertNull($event->toArray()['guestCount']);
        self::assertFalse($event->schedule()->isComplete());
    }

    public function testOvernightEventAndMultipleContactsCanBeConfirmed(): void
    {
        $schedule = new EventSchedule('2026-12-31', 'Europe/Warsaw', new \DateTimeImmutable('2026-12-31T17:00:00+01:00'), new \DateTimeImmutable('2026-12-31T18:00:00+01:00'), new \DateTimeImmutable('2027-01-01T03:00:00+01:00'));
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Sylwester', $schedule);
        $event->replaceDetails('Sylwester', null, $schedule, new EventLocation(street: 'Długa 1', city: 'Kraków', postalCode: '30-001'), 120, Uuid::uuid4()->toString(), null);
        $event->replaceContacts([new EventContact('Anna', '+48 123 456 789'), new EventContact('Jan', '+48 987 654 321')]);
        $event->transitionTo(EventStatus::PLANNED);
        $event->transitionTo(EventStatus::CONFIRMED);
        self::assertSame(EventStatus::CONFIRMED, $event->status());
        self::assertCount(2, $event->toArray()['contacts']);
        self::assertSame('2027-01-01T03:00:00+01:00', $event->schedule()->toArray()['endsAt']);
        $this->expectException(BusinessRuleViolation::class);
        $event->replaceContacts([]);
    }

    public function testIncompleteEventCannotBeConfirmed(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Spotkanie', new EventSchedule('2026-12-31'));
        $event->transitionTo(EventStatus::PLANNED);
        $this->expectException(BusinessRuleViolation::class);
        $event->transitionTo(EventStatus::CONFIRMED);
    }

    public function testCancelledEventCannotBeChangedOrReopened(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Spotkanie', new EventSchedule('2026-12-31'));
        $event->transitionTo(EventStatus::CANCELLED);
        $this->expectException(BusinessRuleViolation::class);
        $event->transitionTo(EventStatus::DRAFT);
    }

    /** @dataProvider invalidSchedules */
    public function testInvalidScheduleIsRejected(string $date, string $zone, ?string $ready, ?string $start, ?string $end): void
    {
        $this->expectException(BusinessRuleViolation::class);
        new EventSchedule($date, $zone, $ready ? new \DateTimeImmutable($ready) : null, $start ? new \DateTimeImmutable($start) : null, $end ? new \DateTimeImmutable($end) : null);
    }

    public static function invalidSchedules(): array
    {
        return [
            ['2026-02-30', 'Europe/Warsaw', null, null, null],
            ['2026-12-31', 'Unknown/Zone', null, null, null],
            ['2026-12-31', 'Europe/Warsaw', '2026-12-31T20:00:00+01:00', '2026-12-31T18:00:00+01:00', null],
            ['2026-12-31', 'Europe/Warsaw', null, '2026-12-31T18:00:00+01:00', '2026-12-31T18:00:00+01:00'],
            ['2026-12-31', 'Europe/Warsaw', null, '2027-01-01T18:00:00+01:00', null],
        ];
    }

    public function testDaylightSavingRepeatedHourUsesExplicitOffsets(): void
    {
        $schedule = new EventSchedule('2026-10-25', 'Europe/Warsaw', null, new \DateTimeImmutable('2026-10-25T02:30:00+02:00'), new \DateTimeImmutable('2026-10-25T02:30:00+01:00'));
        self::assertSame(3600, $schedule->endsAt->getTimestamp() - $schedule->startsAt->getTimestamp());
    }
}
