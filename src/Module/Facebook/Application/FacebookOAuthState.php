<?php

declare(strict_types=1);

namespace App\Module\Facebook\Application;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Saas\Infrastructure\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FacebookOAuthState
{
    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] private string $secret,
        private TenantContext $tenant,
    ) {
    }

    public function issue(string $userUuid): string
    {
        $payload = [
            'tenant' => $this->tenant->current()['uuid'],
            'user' => $userUuid,
            'exp' => time() + 600,
            'nonce' => bin2hex(random_bytes(8)),
        ];
        $body = rtrim(strtr(base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, $this->secret);

        return $body.'.'.$signature;
    }

    public function assertValid(string $state, string $userUuid): void
    {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            throw new BusinessRuleViolation('Nieprawidłowy stan połączenia Facebook.');
        }
        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, $this->secret);
        if (!hash_equals($expected, $signature)) {
            throw new BusinessRuleViolation('Nieprawidłowy podpis stanu Facebook.');
        }
        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            throw new BusinessRuleViolation('Nieprawidłowy stan połączenia Facebook.');
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || ($payload['tenant'] ?? null) !== $this->tenant->current()['uuid'] || ($payload['user'] ?? null) !== $userUuid || (int) ($payload['exp'] ?? 0) < time()) {
            throw new BusinessRuleViolation('Sesja połączenia Facebook wygasła. Spróbuj ponownie.');
        }
    }
}
