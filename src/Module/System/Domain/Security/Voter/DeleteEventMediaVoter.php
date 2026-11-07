<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Security\Voter;

use App\Module\System\Domain\Enum\Permission\PermissionEnum;

final class DeleteEventMediaVoter extends AccessPermissionVoter
{
    protected function getAttributeName(): string
    {
        return PermissionEnum::DELETE_EVENT_MEDIA->value;
    }
}
