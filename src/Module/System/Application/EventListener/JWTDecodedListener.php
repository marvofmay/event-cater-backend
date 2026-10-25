<?php

declare(strict_types=1);

namespace App\Module\System\Application\EventListener;

use App\Module\System\Domain\Service\AuthEvent\BlacklistTokenService;
use App\Module\System\Domain\ValueObject\TokenUUID;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded', method: 'onJWTDecoded')]
final readonly class JWTDecodedListener
{
    public function __construct(private BlacklistTokenService $blacklist, private \App\Module\Saas\Infrastructure\TenantContext $tenantContext, private \App\Module\Saas\Infrastructure\ControlPlane $control)
    {
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        if (!$this->tenantContext->matches($payload) || !isset($payload['tokenUUID'])) {
            $event->markAsInvalid();

            return;
        }

        $tokenUUID = $payload['tokenUUID'];
        if (isset($payload['saasAdministratorUuid'])) {
            $admin = $this->control->technicalAdministrator($payload['username'] ?? $payload['email'] ?? '');
            if (!$admin || !$admin['active'] || $admin['uuid'] !== $payload['saasAdministratorUuid']) {
                $event->markAsInvalid();

                return;
            }
        }
        if ($this->blacklist->isRevoked(TokenUUID::fromString($tokenUUID))) {
            $event->markAsInvalid();
        }
    }
}
