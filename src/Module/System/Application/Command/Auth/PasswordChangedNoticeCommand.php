<?php

declare(strict_types=1);

namespace App\Module\System\Application\Command\Auth;

final readonly class PasswordChangedNoticeCommand
{
    public function __construct(public string $userUuid, public string $email) {}
}
