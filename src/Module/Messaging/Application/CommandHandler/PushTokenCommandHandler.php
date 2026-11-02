<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Messaging\Application\Command\RegisterPushToken;
use App\Module\Messaging\Application\Command\UnregisterPushToken;
use App\Module\Messaging\Domain\Repository\PushTokenRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PushTokenCommandHandler
{
    private const array PLATFORMS = ['ios', 'android', 'web'];

    public function __construct(private PushTokenRepository $tokens)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function register(RegisterPushToken $command): void
    {
        $token = trim($command->token);
        $platform = strtolower(trim($command->platform));
        if ($token === '' || mb_strlen($token) > 255) {
            throw new BusinessRuleViolation('Nieprawidłowy token push.');
        }
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new BusinessRuleViolation('Nieprawidłowa platforma push.');
        }
        if (!str_starts_with($token, 'ExponentPushToken[') && !str_starts_with($token, 'ExpoPushToken[')) {
            throw new BusinessRuleViolation('Oczekiwano tokenu Expo Push.');
        }

        $this->tokens->upsert($command->userUuid, $token, $platform);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function unregister(UnregisterPushToken $command): void
    {
        $token = trim($command->token);
        if ($token === '') {
            throw new BusinessRuleViolation('Nieprawidłowy token push.');
        }
        $this->tokens->removeToken($command->userUuid, $token);
    }
}
