<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;

final readonly class EventSchedule
{
    public function __construct(
        public string $date,
        public string $timezone = 'Europe/Warsaw',
        public ?\DateTimeImmutable $readyAt = null,
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
    ) {
        foreach ([$readyAt, $startsAt, $endsAt] as $time) {
            if ($time !== null && $time->format('u') !== '000000') {
                throw new BusinessRuleViolation('Godziny wydarzenia muszą mieć dokładność do pełnych sekund.');
            }
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new BusinessRuleViolation('Data musi mieć format RRRR-MM-DD.');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new BusinessRuleViolation('Nieznana strefa czasowa.');
        }
        if (($readyAt && $startsAt && $readyAt > $startsAt)
            || ($startsAt && $endsAt && $startsAt >= $endsAt)
            || ($readyAt && $endsAt && $readyAt >= $endsAt)) {
            throw new BusinessRuleViolation('Wymagana kolejność: gotowość ≤ start < koniec.');
        }
        if ($startsAt && $startsAt->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d') !== $date) {
            throw new BusinessRuleViolation('Godzina startu musi wypadać w dniu wydarzenia (w jego strefie czasowej).');
        }
    }

    public function isComplete(): bool
    {
        return $this->readyAt !== null && $this->startsAt !== null && $this->endsAt !== null;
    }

    public function toArray(): array
    {
        $zone = new \DateTimeZone($this->timezone);
        return [
            'date' => $this->date,
            'timezone' => $this->timezone,
            'readyAt' => $this->readyAt?->setTimezone($zone)->format(DATE_ATOM),
            'startsAt' => $this->startsAt?->setTimezone($zone)->format(DATE_ATOM),
            'endsAt' => $this->endsAt?->setTimezone($zone)->format(DATE_ATOM),
        ];
    }
}
