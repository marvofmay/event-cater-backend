<?php

declare(strict_types=1);

namespace App\tests\unit\module\kitchen;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use App\Module\Kitchen\Domain\ValueObject\KitchenLine;
use App\Module\Kitchen\Domain\ValueObject\KitchenPlan;
use App\Module\Kitchen\Infrastructure\PDF\KitchenPdfRenderer;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class KitchenOrderTest extends TestCase
{
    private function source(): array
    {
        return ['eventUuid' => Uuid::uuid4()->toString(), 'eventVersion' => 2, 'eventStatus' => 'planned', 'number' => 'EVT-1', 'title' => 'Przyjęcie', 'date' => '2026-12-31', 'timezone' => 'Europe/Warsaw', 'readyAt' => '2026-12-31T10:00:00+00:00', 'guestCount' => 100, 'menu' => []];
    }
    private function plan(int $quantity = 100, ?string $lineUuid = null): KitchenPlan
    {
        return new KitchenPlan('2026-12-30', new \DateTimeImmutable('2026-12-31T09:00:00+00:00'), Uuid::uuid4()->toString(), 'Anna', null, [new KitchenLine($lineUuid ?? Uuid::uuid4()->toString(), 'Zupa', $quantity)]);
    }
    public function testUnrelatedEventVersionDoesNotInvalidateKitchen(): void
    {
        $source = $this->source();
        $order = KitchenOrder::create(Uuid::uuid4()->toString(), $source, $this->plan());
        $source['eventVersion'] = 55;
        $source['eventStatus'] = 'confirmed';
        self::assertFalse($order->sourceChanged($source));
        $source['guestCount'] = 120;
        self::assertTrue($order->sourceChanged($source));
    }
    public function testSourceHashSurvivesJsonObjectKeyReordering(): void
    {
        $source = $this->source();
        $source['menu'] = [['name' => 'Zupa', 'portions' => 100, 'diets' => ['wegańska']]];
        $reordered = array_reverse($source, true);
        $reordered['menu'][0] = array_reverse($source['menu'][0], true);
        self::assertSame(KitchenOrder::sourceHash($source), KitchenOrder::sourceHash($reordered));
    }
    public function testAcceptingLowerDemandPreservesExcessAndHandoverHistory(): void
    {
        $source = $this->source();
        $plan = $this->plan();
        $line = $plan->lines[0]->uuid;
        $actor = Uuid::uuid4()->toString();
        $order = KitchenOrder::create(Uuid::uuid4()->toString(), $source, $plan);
        $order->release($source, $actor);
        $order->record($line, 100, 90, null, $actor);
        $order->propose($this->plan(80, $line), $source, 'Zmniejszenie liczby zamówionych porcji', $actor);
        $order->acceptRevision($source, $actor);
        $data = $order->toArray();
        self::assertSame(100, $data['lines'][0]['preparedPortions']);
        self::assertSame(90, $data['lines'][0]['handedOverPortions']);
        self::assertSame(20, $data['lines'][0]['excessPreparedPortions']);
        self::assertSame(2, $data['revision']);
    }
    /** @dataProvider invalidCounts */
    public function testRejectsInvalidCounts(int $prepared, int $handed): void
    {
        $source = $this->source();
        $plan = $this->plan();
        $order = KitchenOrder::create(Uuid::uuid4()->toString(), $source, $plan);
        $order->release($source, Uuid::uuid4()->toString());
        $this->expectException(BusinessRuleViolation::class);
        $order->record($plan->lines[0]->uuid, $prepared, $handed, null, Uuid::uuid4()->toString());
    }
    public static function invalidCounts(): array
    {
        return [[-1, 0], [0, 1], [1000001, 0], [10, -1]];
    }
    public function testCorrectingSignedHandoverInvalidatesSignoff(): void
    {
        $source = $this->source();
        $plan = $this->plan();
        $line = $plan->lines[0]->uuid;
        $actor = Uuid::uuid4()->toString();
        $order = KitchenOrder::create(Uuid::uuid4()->toString(), $source, $plan);
        $order->release($source, $actor);
        $order->record($line, 100, 100, null, $actor);
        $order->ready($source);
        $order->handOver($source, $actor);
        $order->record($line, null, 90, 'Korekta błędnego przekazania dziesięciu porcji', $actor);
        self::assertSame('in_production', $order->toArray()['status']);
        self::assertNull($order->toArray()['handedOverAt']);
    }
    public function testNewMenuBlocksStartingAnOutdatedCard(): void
    {
        $source = $this->source();
        $order = KitchenOrder::create(Uuid::uuid4()->toString(), $source, $this->plan());
        $order->release($source, Uuid::uuid4()->toString());
        $source['menu'] = [['name' => 'Inna zupa']];
        $this->expectException(PlanningConflict::class);
        $order->start($source);
    }
    public function testPdfEscapesUserContentAndMarksObsoleteRevision(): void
    {
        $source = $this->source();
        $source['title'] = '<img src="http://example.invalid/private">';
        $plan = $this->plan()->toArray();
        $plan['lines'][0]['name'] = '<script>alert(1)</script>';
        $html = (new KitchenPdfRenderer())->html(['snapshot' => ['source' => $source, 'plan' => $plan], 'orderNumber' => 'KIT-1', 'number' => 1, 'currentRevision' => 2, 'superseded' => true, 'orderStatus' => 'cancelled', 'eventStatus' => 'planned', 'sourceChanged' => true, 'pendingRevision' => true]);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('NIEAKTUALNA REWIZJA', $html);
        self::assertStringContainsString('ANULOWANO', $html);
        self::assertStringContainsString('ZMIENIONO WYDARZENIE', $html);
    }
    public function testDuplicateLineIdentifiersAreRejected(): void
    {
        $plan = $this->plan();
        $this->expectException(BusinessRuleViolation::class);
        new KitchenPlan($plan->productionDate, $plan->dueAt, $plan->responsibleUuid, null, null, [$plan->lines[0], $plan->lines[0]]);
    }
}
