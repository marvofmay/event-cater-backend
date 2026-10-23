<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Security\Voter;

use App\Module\System\Domain\Enum\Permission\PermissionEnum;

final class PrepareEventCateringVoter extends AccessPermissionVoter
{
    protected function getAttributeName(): string
    {
        return PermissionEnum::PREPARE_EVENT_CATERING->value;
    }
}
