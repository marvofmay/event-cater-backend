<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\Command;

final readonly class RegisterPushToken
{
    public function __construct(
        public string $userUuid,
        public string $token,
        public string $platform,
    ) {
    }
}
