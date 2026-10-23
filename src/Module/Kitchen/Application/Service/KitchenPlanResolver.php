<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Service;

use App\Module\Kitchen\Application\Port\KitchenStaffDirectory;
use App\Module\Kitchen\Domain\ValueObject\KitchenLine;
use App\Module\Kitchen\Domain\ValueObject\KitchenPlan;
use Ramsey\Uuid\Uuid;

final readonly class KitchenPlanResolver
{
    public function __construct(private KitchenStaffDirectory $staff)
    {
    }
    public function fromMenu(array $source, ?string $productionDate, ?\DateTimeImmutable $dueAt, ?string $responsibleUuid, ?string $notes, string $serviceMode = 'off_site'): KitchenPlan
    {
        $lines = array_map(static fn (array $item) => new KitchenLine(Uuid::uuid4()->toString(), $item['name'], $item['portions'], $item['portionSize'], $item['diets'], $item['allergens'], $item['servingStyle'], $item['notes']), $source['menu']);
        return $this->resolve(new KitchenPlan($productionDate ?? $source['date'], $dueAt ?? ($source['readyAt'] === null ? null : new \DateTimeImmutable($source['readyAt'])), $responsibleUuid, null, $notes, $lines, $serviceMode));
    }
    public function resolve(KitchenPlan $plan): KitchenPlan
    {
        $ids = array_filter([$plan->responsibleUuid, ...array_map(static fn (KitchenLine $line) => $line->responsibleUuid, $plan->lines)]);
        $ids = array_values(array_unique($ids));
        sort($ids);
        $names = [];
        foreach ($ids as $id) {
            $names[$id] = $this->staff->requireActive($id);
        }
        return new KitchenPlan(
            $plan->productionDate,
            $plan->dueAt,
            $plan->responsibleUuid,
            $names[$plan->responsibleUuid] ?? null,
            $plan->notes,
            array_map(static fn (KitchenLine $line) => new KitchenLine($line->uuid, $line->name, $line->requiredPortions, $line->portionSize, $line->diets, $line->allergens, $line->packing, $line->notes, $line->responsibleUuid, $names[$line->responsibleUuid] ?? null), $plan->lines),
            $plan->serviceMode
        );
    }
    public function revalidate(array $plan): void
    {
        $ids = array_filter([$plan['responsibleUuid'], ...array_column($plan['lines'], 'responsibleUuid')]);
        $ids = array_values(array_unique($ids));
        sort($ids);
        foreach ($ids as $id) {
            $this->staff->requireActive($id);
        }
    }
}
