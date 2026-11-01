<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Query;

final readonly class SearchMessageRecipients
{
    public function __construct(public string $search)
    {
    }
}
