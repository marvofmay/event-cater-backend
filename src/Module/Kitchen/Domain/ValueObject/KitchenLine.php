<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;

final readonly class KitchenLine
{
    public function __construct(
        public string $uuid,
        public string $name,
        public int $requiredPortions,
        public ?string $portionSize = null,
        public array $diets = [],
        public array $allergens = [],
        public ?string $packing = null,
        public ?string $notes = null,
        public ?string $responsibleUuid = null,
        public ?string $responsibleName = null,
    ) {
        if (!Uuid::isValid($uuid) || trim($name) === '' || mb_strlen($name) > 200 || $requiredPortions < 0 || $requiredPortions > 1000000) {
            throw new BusinessRuleViolation('Kitchen line requires a UUID, name and 0..1000000 portions.');
        }
        foreach ([$portionSize, $packing] as $value) {
            if ($value !== null && mb_strlen($value) > 500) {
                throw new BusinessRuleViolation('Portion size and packing must not exceed 500 characters.');
            }
        }
        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new BusinessRuleViolation('Kitchen notes must not exceed 4000 characters.');
        }
        if ($responsibleUuid !== null && !Uuid::isValid($responsibleUuid)) {
            throw new BusinessRuleViolation('Invalid responsible employee UUID.');
        }
        foreach ([$diets, $allergens] as $labels) {
            if (!array_is_list($labels) || count($labels) > 30) {
                throw new BusinessRuleViolation('Diets and allergens must be lists of up to 30 labels.');
            }
            foreach ($labels as $label) {
                if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 100) {
                    throw new BusinessRuleViolation('Diet and allergen labels require 1..100 characters.');
                }
            }
        }
    }
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
