<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\ConsumableCatalog;
use App\Module\Event\Application\Port\EquipmentCatalog;
use App\Module\Event\Domain\ValueObject\PreparationRule;

final readonly class PreparationTemplateResolver
{
    public function __construct(private EquipmentCatalog $equipment, private ConsumableCatalog $consumables)
    {
    }

    public function resolve(array $definitions): array
    {
        $resolved = [];
        // Deterministic catalog locking order reduces competing template-edit deadlocks.
        usort($definitions, static fn (array $a, array $b) => [$a['kind'], $a['itemUuid']] <=> [$b['kind'], $b['itemUuid']]);
        foreach ($definitions as $definition) {
            $snapshot = match ($definition['kind']) {
                'equipment' => $this->equipment->requireActive($definition['itemUuid']),
                'consumable' => $this->consumables->requireActive($definition['itemUuid']),
                default => throw new BusinessRuleViolation('Unknown template item kind.'),
            };
            PreparationRule::restore($definition['rule']);
            $resolved[] = [...$definition, 'name' => $snapshot['name'], 'code' => $snapshot['code'], 'unit' => $snapshot['unit']];
        }
        return $resolved;
    }
}
