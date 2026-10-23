<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Query;

final readonly class GetKitchenOrder
{
    public function __construct(public string $uuid)
    {
    }
}
