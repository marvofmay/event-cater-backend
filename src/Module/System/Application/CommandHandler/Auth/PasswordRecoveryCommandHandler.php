<?php

declare(strict_types=1);

namespace App\Module\System\Application\CommandHandler\Auth;

use App\Module\System\Application\Command\Auth\RequestPasswordResetCommand;
use App\Module\System\Application\Command\Auth\PasswordChangedNoticeCommand;
use App\Module\System\Domain\Service\User\PasswordRecoveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PasswordRecoveryCommandHandler
{
    public function __construct(private PasswordRecoveryService $recovery) {}

    #[AsMessageHandler(bus: 'command.bus')]
    public function request(RequestPasswordResetCommand $command): void { $this->recovery->issue($command); }

    #[AsMessageHandler(bus: 'command.bus')]
    public function notify(PasswordChangedNoticeCommand $command): void { $this->recovery->notify($command->userUuid, $command->email); }
}
