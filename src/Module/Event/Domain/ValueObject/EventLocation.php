<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;

final readonly class EventLocation
{
    public function __construct(
        public ?string $venue = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $instructions = null,
    ) {
        foreach (['venue' => 200, 'street' => 200, 'city' => 100, 'postalCode' => 20, 'instructions' => 4000] as $field => $limit) {
            if ($this->$field !== null && mb_strlen($this->$field) > $limit) {
                throw new BusinessRuleViolation(sprintf('%s exceeds %d characters.', $field, $limit));
            }
        }
    }

    public function isComplete(): bool
    {
        return trim($this->street ?? '') !== '' && trim($this->city ?? '') !== '' && trim($this->postalCode ?? '') !== '';
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
