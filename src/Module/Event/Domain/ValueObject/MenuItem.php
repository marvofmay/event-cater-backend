<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;

final readonly class MenuItem
{
    /** @param list<string> $diets @param list<string> $allergens */
    public function __construct(
        public string $name,
        public int $portions,
        public ?string $section = null,
        public ?string $portionSize = null,
        public ?string $servingStyle = null,
        public array $diets = [],
        public array $allergens = [],
        public ?string $notes = null,
    ) {
        if (trim($name) === '' || mb_strlen($name) > 200 || $portions < 1 || $portions > 1000000) {
            throw new BusinessRuleViolation('Menu item requires a name and 1..1000000 portions.');
        }
        foreach ([$section, $portionSize, $servingStyle] as $value) {
            if ($value !== null && mb_strlen($value) > 100) {
                throw new BusinessRuleViolation('Menu description field exceeds 100 characters.');
            }
        }
        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new BusinessRuleViolation('Menu notes exceed 4000 characters.');
        }
        foreach ([$diets, $allergens] as $values) {
            if (!array_is_list($values) || count($values) > 30) {
                throw new BusinessRuleViolation('Diets and allergens must be lists of up to 30 labels.');
            }
            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 100) {
                    throw new BusinessRuleViolation('Diet and allergen labels must contain 1..100 characters.');
                }
            }
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
