<?php

declare(strict_types=1);

namespace App\Common\Application\DTO;

use App\Common\Domain\Exception\BusinessRuleViolation;

final readonly class Page
{
    public function __construct(public int $number = 1, public int $limit = 20)
    {
        if ($number < 1 || $number > 100000 || $limit < 1 || $limit > 100) {
            throw new BusinessRuleViolation('page must be 1..100000 and limit 1..100.');
        }
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->limit;
    }
    public function result(array $items, int $total): array
    {
        return ['items' => $items, 'total' => $total, 'page' => $this->number, 'limit' => $this->limit];
    }
}
