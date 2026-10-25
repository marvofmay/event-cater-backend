<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Query;

final readonly class GetConsumableCategory
{
    public function __construct(public string $uuid)
    {
    }
}
