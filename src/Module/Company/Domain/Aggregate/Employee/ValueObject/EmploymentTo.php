<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\Employee\ValueObject;

class EmploymentTo extends EmploymentDateAbstract
{
    protected ?\DateTimeImmutable $date;

    public static function fromString(?string $dateString, ?EmploymentFrom $from): static
    {
        $instance = new static($dateString);

        if (null !== $from && null !== $instance->date && $instance->date <= $from->toDateTime()) {
            throw new \InvalidArgumentException('EmploymentTo must be after EmploymentFrom.');
        }

        return $instance;
    }
}
