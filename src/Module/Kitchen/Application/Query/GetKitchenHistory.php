<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Query;

final readonly class GetKitchenHistory
{
    public function __construct(public string $uuid, public \App\Common\Application\DTO\Page $page)
    {
    }
}
