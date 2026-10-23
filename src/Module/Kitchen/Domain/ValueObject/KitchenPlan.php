<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Domain\ValueObject;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;

final readonly class KitchenPlan
{
    /** @param array $lines Validated at this domain boundary. */
    public function __construct(
        public string $productionDate,
        public ?\DateTimeImmutable $dueAt,
        public ?string $responsibleUuid,
        public ?string $responsibleName,
        public ?string $notes,
        public array $lines,
        public string $serviceMode = 'off_site',
    ) {
        self::validateDate($productionDate);
        if (!in_array($serviceMode, ['off_site', 'on_site'], true)) {
            throw new BusinessRuleViolation('serviceMode must be off_site or on_site.');
        }
        if ($responsibleUuid !== null && !Uuid::isValid($responsibleUuid)) {
            throw new BusinessRuleViolation('Invalid responsible employee UUID.');
        }
        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new BusinessRuleViolation('Kitchen plan notes must not exceed 4000 characters.');
        }
        if (!array_is_list($lines) || count($lines) > 300) {
            throw new BusinessRuleViolation('A kitchen order supports at most 300 lines.');
        }
        $ids = [];
        foreach ($lines as $line) {
            if (!$line instanceof KitchenLine || isset($ids[$line->uuid])) {
                throw new BusinessRuleViolation('Kitchen lines require unique UUIDs.');
            }
            $ids[$line->uuid] = true;
        }
    }
    public static function validateDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new BusinessRuleViolation('productionDate must be a valid YYYY-MM-DD date.');
        }
    }
    public function toArray(): array
    {
        return ['serviceMode' => $this->serviceMode, 'productionDate' => $this->productionDate, 'dueAt' => $this->dueAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'responsibleUuid' => $this->responsibleUuid, 'responsibleName' => $this->responsibleName, 'notes' => $this->notes,
            'lines' => array_map(static fn (KitchenLine $line) => $line->toArray(), $this->lines)];
    }
}
