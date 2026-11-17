<?php

declare(strict_types=1);

namespace App\Module\Support\Application;

final readonly class Reporter
{
    public function __construct(
        public string $userUuid,
        public string $email,
        public string $name,
        public string $tenantUuid,
        public string $tenantSlug,
        public string $tenantName,
    ) {
    }
}
