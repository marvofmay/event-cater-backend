<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

final class TpaySignature
{
    public static function valid(string $rawBody, string $jws, string $certificatePem, string $rootPem): bool
    {
        $parts = explode('.', $jws);
        if (count($parts) < 3 || $parts[0] === '' || $parts[2] === '') {
            return false;
        }
        $headerJson = self::base64UrlDecode($parts[0]);
        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256') {
            return false;
        }
        if (1 !== openssl_x509_verify($certificatePem, $rootPem)) {
            return false;
        }
        $publicKey = openssl_pkey_get_public($certificatePem);
        if (false === $publicKey) {
            return false;
        }
        $payload = rtrim(strtr(base64_encode($rawBody), '+/', '-_'), '=');
        $signature = self::base64UrlDecode($parts[2]);
        if ($signature === '') {
            return false;
        }

        return 1 === openssl_verify($parts[0].'.'.$payload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    }

    public static function certificateUrl(string $jws): ?string
    {
        $parts = explode('.', $jws);
        if ($parts[0] === '') {
            return null;
        }
        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        $x5u = is_array($header) ? ($header['x5u'] ?? null) : null;

        return is_string($x5u) && $x5u !== '' ? $x5u : null;
    }

    public static function certificateUrlAllowed(string $x5u, string $secureOrigin): bool
    {
        $origin = rtrim($secureOrigin, '/');
        $parts = parse_url($x5u);
        $expected = parse_url($origin);
        if (!is_array($parts) || !is_array($expected)) {
            return false;
        }
        $path = $parts['path'] ?? '';

        return ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === strtolower((string) ($expected['host'] ?? ''))
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['port'])
            && !isset($parts['query'])
            && str_starts_with($path, '/x509/')
            && str_ends_with($path, '.pem');
    }

    public static function checksum(string $merchantId, string $transactionTitle, string $amount, string $crc, string $securityCode): string
    {
        return md5($merchantId.$transactionTitle.$amount.$crc.$securityCode);
    }

    public static function cents(string $amount): ?int
    {
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $amount)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $decoded ? '' : $decoded;
    }
}
