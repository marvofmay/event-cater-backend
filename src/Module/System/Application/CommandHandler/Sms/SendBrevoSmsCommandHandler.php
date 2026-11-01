<?php

declare(strict_types=1);

namespace App\Module\System\Application\CommandHandler\Sms;

use App\Module\System\Application\Command\Sms\SendBrevoSmsCommand;
use App\Module\System\Infrastructure\Sms\BrevoSmsSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendBrevoSmsCommandHandler
{
    public function __construct(private BrevoSmsSender $sender)
    {
    }

    public function __invoke(SendBrevoSmsCommand $command): void
    {
        $this->sender->send($command->id, $command->userUuid, $command->recipient, $command->content);
    }
}
