<?php

declare(strict_types=1);

namespace App\Module\System\Application\Command\Auth;

final readonly class RequestPasswordResetCommand
{
    public function __construct(public string $login, public string $requestUuid, public int $requestedAt) {}
}
