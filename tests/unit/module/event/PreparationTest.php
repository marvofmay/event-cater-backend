<?php

declare(strict_types=1);

namespace App\tests\unit\module\event;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\ValueObject\Quantity;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Entity\PreparationLine;
use App\Module\Event\Domain\Enum\PreparationStatus;
use App\Module\Event\Domain\ValueObject\EventSchedule;
use App\Module\Event\Domain\ValueObject\PreparationRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PreparationTest extends TestCase
{
    /** @dataProvider quantities */
    public function testExactQuantities(int|string $input, string $canonical): void
    {
        self::assertSame($canonical, Quantity::from($input)->decimal());
    }
    public static function quantities(): array
    {
        return [[0, '0.000'], [20, '20.000'], ['0.125', '0.125'], ['1.2', '1.200'], ['1000000', '1000000.000']];
    }

    /** @dataProvider invalidQuantities */
    public function testInvalidQuantities(string $value): void
    {
        $this->expectException(BusinessRuleViolation::class);
        Quantity::from($value);
    }
    public static function invalidQuantities(): array
    {
        return [['-1'], ['1e3'], ['1.0001'], ['1000000.001'], ['01'], ['NaN']];
    }

    public function testWholePackagesCannotHaveFractionalProgress(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        Quantity::from('0.5')->assertUnit('carton');
    }

    public function testRulesRoundUpInTargetUnitsWithoutFloatArithmetic(): void
    {
        self::assertSame('3.000', (new PreparationRule('per_guest', Quantity::from(1), 50))->calculate(120, 'piece')->decimal());
        self::assertSame('110.000', (new PreparationRule('per_guest', Quantity::from(1), 1, 10))->calculate(100, 'set')->decimal());
        self::assertSame('2.400', (new PreparationRule('per_guest', Quantity::from('0.020'), 1))->calculate(120, 'kg')->decimal());
        self::assertSame('0.001', (new PreparationRule('per_guest', Quantity::from('0.001'), 100))->calculate(1, 'l')->decimal());
        $this->expectException(BusinessRuleViolation::class);
        (new PreparationRule('per_guest', Quantity::from(1000000), 1, 100))->calculate(1000000, 'piece');
    }

    public function testRulesDoNotInventGuestCounts(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        (new PreparationRule('per_guest', Quantity::from(1), 10))->calculate(null, 'piece');
    }

    public function testPlanChangesPreservePhysicalCountsAndRevokeDispatch(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Bankiet', new EventSchedule('2026-12-31'));
        $list = new EventPreparationList($event);
        $gear = new EquipmentRequirement($event, Uuid::uuid4()->toString(), 'Bulionówki', 'BOWL', 'piece', 100, null);
        $line = PreparationLine::equipment($list, $gear);
        $list->add($line);
        $actor = Uuid::uuid4()->toString();
        $list->configure($actor, 'Anna', null, null);
        $list->approve($actor);
        $line->recordProgress(Quantity::from(100), Quantity::from(80), $actor, null, $list->isApproved());
        $list->afterProgressChanged();
        $list->confirmLoaded($actor, 'Koordynator zatwierdza brak dwudziestu sztuk.');
        self::assertSame(PreparationStatus::LOADED, $list->status());
        self::assertSame('20.000', $list->toArray()['acceptedShortages'][0]['missingToLoad']);
        $gear->changeQuantity(120, null);
        $list->invalidatePlan();
        self::assertSame('40.000', $line->missingToLoad()->decimal());
        self::assertSame('100.000', $line->prepared()->decimal());
        self::assertSame(PreparationStatus::DRAFT, $list->status());
        self::assertNull($list->toArray()['approvedRevision']);
        self::assertNull($list->toArray()['acceptedShortagesReason']);
    }

    public function testOveragesAreVisibleAndNotSilentlyRemoved(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Bankiet', new EventSchedule('2026-12-31'));
        $list = new EventPreparationList($event);
        $actor = Uuid::uuid4()->toString();
        $line = PreparationLine::consumable($list, ['uuid' => Uuid::uuid4()->toString(), 'name' => 'Mleko', 'code' => 'MILK', 'unit' => 'carton'], Quantity::from(20));
        $list->add($line);
        $list->configure($actor, 'Anna', null, null);
        $list->approve($actor);
        $line->recordProgress(Quantity::from(22), Quantity::from(20), $actor, null, true);
        $list->afterProgressChanged();
        $list->markReady();
        $list->confirmLoaded($actor, null);
        self::assertTrue($list->toArray()['summary']['hasSurplus']);
        self::assertSame('2.000', $line->toArray()['extraPrepared']);
        self::assertFalse($list->toArray()['summary']['hasShortages']);
    }

    public function testCannotApproveEmptyList(): void
    {
        $event = CateringEvent::create(Uuid::uuid4()->toString(), 'Bankiet', new EventSchedule('2026-12-31'));
        $this->expectException(BusinessRuleViolation::class);
        (new EventPreparationList($event))->approve(Uuid::uuid4()->toString());
    }
}
