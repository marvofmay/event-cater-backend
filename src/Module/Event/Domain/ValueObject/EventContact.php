<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;

final readonly class EventContact
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email = null,
        public ?string $function = null,
    ) {
        if (trim($name) === '' || mb_strlen($name) > 200) {
            throw new BusinessRuleViolation('Contact name is required (maximum 200 characters).');
        }
        if (!preg_match('/^\+?[0-9 ()\-.]{6,30}$/D', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 6) {
            throw new BusinessRuleViolation('A valid contact phone is required.');
        }
        if ($email !== null && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new BusinessRuleViolation('Invalid contact email.');
        }
        if ($function !== null && mb_strlen($function) > 100) {
            throw new BusinessRuleViolation('Contact function is too long.');
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
