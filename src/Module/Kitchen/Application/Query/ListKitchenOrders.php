<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Query;

final readonly class ListKitchenOrders
{
    public function __construct(public string $date, public ?string $status, public \App\Common\Application\DTO\Page $page)
    {
    }
}
