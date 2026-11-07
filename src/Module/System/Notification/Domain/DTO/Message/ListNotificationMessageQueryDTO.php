<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\DTO\Message;

use App\Common\Domain\Abstract\QueryDTOAbstract;

class ListNotificationMessageQueryDTO extends QueryDTOAbstract
{
    public ?string $title = null;
    public ?string $content = null;
    public ?string $userUUID = null;
    public ?string $channelCode = null;
    /** true = nieprzeczytane, false = przeczytane, null/puste = wszystkie */
    public ?string $unread = null;
}
