<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Query;

final readonly class ListMessages
{
    public function __construct(public string $userUuid, public \App\Common\Application\DTO\Page $page, public string $folder, public bool $unread, public string $search, public string $sortBy = 'createdAt', public string $sortDirection = 'desc')
    {
    }
}
