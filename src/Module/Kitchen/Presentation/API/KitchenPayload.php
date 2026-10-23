<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Presentation\API;

use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Kitchen\Domain\ValueObject\KitchenLine;
use App\Module\Kitchen\Domain\ValueObject\KitchenPlan;
use Ramsey\Uuid\Uuid;

final class KitchenPayload
{
    public static function plan(PlanningPayload $body): KitchenPlan
    {
        $lines = array_map(static function (mixed $value): KitchenLine {
            $line = PlanningPayload::item($value);
            return new KitchenLine(
                $line->uuid('uuid', false) ?? Uuid::uuid4()->toString(),
                $line->text('name'),
                $line->integer('requiredPortions'),
                $line->text('portionSize', false),
                $line->labels('diets'),
                $line->labels('allergens'),
                $line->text('packing', false),
                $line->text('notes', false),
                $line->uuid('responsibleUuid', false)
            );
        }, $body->list('lines', 300));
        return new KitchenPlan($body->text('productionDate'), $body->dateTime('dueAt'), $body->uuid('responsibleUuid', false), null, $body->text('notes', false), $lines, $body->text('serviceMode', false) ?? 'off_site');
    }
    public static function eventVersion(PlanningPayload $body): int
    {
        $version = $body->integer('eventVersion');
        if ($version < 1) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('eventVersion must be positive.');
        }
        return $version;
    }
}
