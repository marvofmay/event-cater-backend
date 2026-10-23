<?php

declare(strict_types=1);

namespace App\Common\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;

/** Exact arithmetic in thousandths; JSON decimal floats are deliberately rejected. */
final readonly class Quantity
{
    private function __construct(private int $milliunits)
    {
    }

    public static function from(int|string $value): self
    {
        $text = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,3}))?$/D', $text, $parts)) {
            throw new BusinessRuleViolation('Quantity must be a nonnegative integer or decimal string with up to 3 decimal places.');
        }
        return self::fromMilliunits((int) $parts[1] * 1000 + (int) str_pad($parts[2] ?? '', 3, '0'));
    }

    public static function fromMilliunits(int $value): self
    {
        if ($value < 0 || $value > 1000000000) {
            throw new BusinessRuleViolation('Quantity must be between 0 and 1000000.000.');
        }
        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }
    public function milliunits(): int
    {
        return $this->milliunits;
    }
    public function isZero(): bool
    {
        return $this->milliunits === 0;
    }
    public function lessThan(self $other): bool
    {
        return $this->milliunits < $other->milliunits;
    }
    public function missingFrom(self $actual): self
    {
        return new self(max(0, $this->milliunits - $actual->milliunits));
    }
    public function excessOver(self $planned): self
    {
        return new self(max(0, $this->milliunits - $planned->milliunits));
    }
    public function decimal(): string
    {
        return intdiv($this->milliunits, 1000).'.'.str_pad((string) ($this->milliunits % 1000), 3, '0', STR_PAD_LEFT);
    }

    public function assertUnit(string $unit): void
    {
        if (!in_array($unit, ['piece', 'set', 'kit', 'carton', 'pack', 'bottle', 'kg', 'l'], true)) {
            throw new BusinessRuleViolation('Unknown preparation unit.');
        }
        if (!in_array($unit, ['kg', 'l'], true) && $this->milliunits % 1000 !== 0) {
            throw new BusinessRuleViolation('This unit requires whole quantities.');
        }
    }
}
