<?php

declare(strict_types=1);

namespace App\Module\Saas\Domain;

/** Commercial lifecycle is independent of database provisioning. Deletion is reversible. */
enum TenantStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public function canTransitionTo(self $next, bool $databaseReady): bool
    {
        if (self::Deleted === $this && self::Inactive !== $next) {
            return false;
        }

        return self::Active !== $next || $databaseReady;
    }
}
