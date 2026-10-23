<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Domain\ValueObject\PreparationRule;

final class PreparationTemplatePayload
{
    public static function lines(PlanningPayload $body): array
    {
        return array_map(static function ($value): array {
            $line = PlanningPayload::item($value);
            $kind = $line->text('kind');
            if (!in_array($kind, ['equipment', 'consumable'], true)) {
                throw new BusinessRuleViolation('kind must be equipment or consumable.');
            }
            $input = $line->object('rule');
            $rule = new PreparationRule($input->text('mode'), $input->quantity('quantity'), $input->integer('perGuests', false), $input->integer('reservePercent', false) ?? 0);
            return ['kind' => $kind, 'itemUuid' => $line->uuid('itemUuid'), 'rule' => $rule->toArray(), 'sourceLocation' => $line->text('sourceLocation', false), 'instructions' => $line->text('instructions', false)];
        }, $body->list('lines', 100));
    }
}
