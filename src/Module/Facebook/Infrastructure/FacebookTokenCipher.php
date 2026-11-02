<?php

declare(strict_types=1);

namespace App\Module\Facebook\Infrastructure;

use App\Module\Saas\Infrastructure\TenantContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FacebookTokenCipher
{
    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] private string $secret,
        private TenantContext $tenant,
    ) {
    }

    public function encrypt(#[\SensitiveParameter] string $value): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($value, $nonce, $this->key()));
    }

    public function decrypt(#[\SensitiveParameter] string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('Nie można odczytać tokenu Facebook.');
        }
        $plain = sodium_crypto_secretbox_open(
            substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key(),
        );
        if ($plain === false) {
            throw new \RuntimeException('Nie można odczytać tokenu Facebook.');
        }

        return $plain;
    }

    private function key(): string
    {
        return hash('sha256', $this->secret.'|facebook|'.$this->tenant->current()['uuid'], true);
    }
}
