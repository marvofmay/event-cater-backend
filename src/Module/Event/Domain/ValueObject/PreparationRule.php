<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\ValueObject\Quantity;

final readonly class PreparationRule
{
    public function __construct(public string $mode, public Quantity $quantity, public ?int $perGuests = null, public int $reservePercent = 0)
    {
        if (!in_array($mode, ['fixed', 'per_guest'], true) || $quantity->isZero() || $reservePercent < 0 || $reservePercent > 100) {
            throw new BusinessRuleViolation('Invalid preparation calculation rule.');
        }
        if ($mode === 'fixed' && ($perGuests !== null || $reservePercent !== 0)) {
            throw new BusinessRuleViolation('Fixed rules have no perGuests or reservePercent.');
        }
        if ($mode === 'per_guest' && ($perGuests === null || $perGuests < 1 || $perGuests > 1000000)) {
            throw new BusinessRuleViolation('per_guest requires perGuests between 1 and 1000000.');
        }
    }

    public function calculate(?int $guestCount, string $unit): Quantity
    {
        if ($this->mode === 'fixed') {
            $this->quantity->assertUnit($unit);
            return $this->quantity;
        }
        if ($guestCount === null || $guestCount < 1 || $guestCount > 1000000) {
            throw new BusinessRuleViolation('Guest count is required for per_guest preparation rules.');
        }
        // Bounded operands fit signed 64-bit integers; round up once in the target unit.
        $numerator = $this->quantity->milliunits() * $guestCount * (100 + $this->reservePercent);
        $denominator = $this->perGuests * 100;
        $step = in_array($unit, ['kg', 'l'], true) ? 1 : 1000;
        $divisor = $denominator * $step;
        return Quantity::fromMilliunits(intdiv($numerator + $divisor - 1, $divisor) * $step);
    }

    public function toArray(): array
    {
        return ['mode' => $this->mode, 'quantity' => $this->quantity->decimal(), 'perGuests' => $this->perGuests, 'reservePercent' => $this->reservePercent];
    }

    public static function restore(array $data): self
    {
        return new self($data['mode'], Quantity::from($data['quantity']), $data['perGuests'], $data['reservePercent']);
    }
}
