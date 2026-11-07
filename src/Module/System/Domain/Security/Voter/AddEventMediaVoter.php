<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Security\Voter;

use App\Module\System\Domain\Enum\Permission\PermissionEnum;

final class AddEventMediaVoter extends AccessPermissionVoter
{
    protected function getAttributeName(): string
    {
        return PermissionEnum::ADD_EVENT_MEDIA->value;
    }
}
