<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\Employee\ValueObject;

use App\Common\Shared\Utils\PESELValidator;

final readonly class PESEL
{
    private string $value;

    public function __construct(string $pesel, private bool $isFake = false)
    {
        $error = $isFake
            ? (preg_match('/^\d{11}$/D', $pesel) ? null : 'PESEL musi zawierać 11 cyfr.')
            : PESELValidator::validate($pesel);

        if (null !== $error) {
            throw new \InvalidArgumentException(sprintf('Invalid PESEL: %s', $error));
        }

        $this->value = $pesel;
    }

    public static function fromString(string $value, bool $isFake = false): self
    {
        return new self($value, $isFake);
    }

    public function isFake(): bool
    {
        return $this->isFake;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
