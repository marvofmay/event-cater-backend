<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TpayClient
{
    private const AMOUNT = '99.00';
    private static ?string $token = null;
    private static int $tokenUntil = 0;

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%env(default:tpay_env_default:TPAY_ENV)%')]
        private string $env,
        #[Autowire('%env(default:tpay_client_id_default:TPAY_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(default:tpay_client_secret_default:TPAY_CLIENT_SECRET)%')]
        #[\SensitiveParameter]
        private string $clientSecret,
        #[Autowire('%env(default:tpay_merchant_id_default:TPAY_MERCHANT_ID)%')]
        private string $merchantId,
        #[Autowire('%env(default:tpay_security_code_default:TPAY_SECURITY_CODE)%')]
        #[\SensitiveParameter]
        private string $securityCode,
    ) {
    }

    public function configured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->merchantId !== '' && $this->securityCode !== '';
    }

    public function sandbox(): bool
    {
        return $this->env !== 'production';
    }

    public function amount(): string
    {
        return self::AMOUNT;
    }

    public function merchantId(): string
    {
        return $this->merchantId;
    }

    public function securityCode(): string
    {
        return $this->securityCode;
    }

    /** @return array{transactionId: string, title: string, paymentUrl: string} */
    public function createBlikTransaction(string $crc, string $payerEmail, string $payerName, string $description, string $notifyUrl, string $successUrl, string $errorUrl): array
    {
        $response = $this->request('POST', '/transactions', [
            'amount' => (float) self::AMOUNT,
            'description' => $description,
            'hiddenDescription' => $crc,
            'payer' => [
                'email' => $payerEmail,
                'name' => $payerName,
            ],
            'pay' => [
                'groupId' => 150,
            ],
            'callbacks' => [
                'notification' => ['url' => $notifyUrl],
                'payerUrls' => [
                    'success' => $successUrl,
                    'error' => $errorUrl,
                ],
            ],
        ]);
        $transactionId = $response['transactionId'] ?? '';
        $paymentUrl = $response['transactionPaymentUrl'] ?? '';
        $title = $response['title'] ?? '';
        if (($response['result'] ?? '') !== 'success' || !is_string($transactionId) || $transactionId === '' || !is_string($paymentUrl) || !$this->paymentUrlAllowed($paymentUrl)) {
            $this->logger->error('Tpay rejected transaction creation.', ['result' => $response['result'] ?? null, 'status' => $response['status'] ?? null]);
            throw new TpayTemporaryException('Tpay nie przyjął płatności. Spróbuj ponownie.');
        }

        return [
            'transactionId' => $transactionId,
            'title' => is_string($title) ? $title : '',
            'paymentUrl' => $paymentUrl,
        ];
    }

    public function transactionPaid(string $transactionId): bool
    {
        if (!preg_match('/^[A-Za-z0-9]{10,64}$/', $transactionId)) {
            return false;
        }
        try {
            $response = $this->request('GET', '/transactions/'.rawurlencode($transactionId));
        } catch (TpayTemporaryException) {
            return false;
        }
        $status = $response['status'] ?? '';
        $amount = $response['amount'] ?? null;
        $paid = $response['payments']['amountPaid'] ?? $amount;
        if (!in_array($status, ['paid', 'correct'], true)) {
            return false;
        }
        if (TpaySignature::cents($this->money($amount)) !== TpaySignature::cents(self::AMOUNT)) {
            return false;
        }

        return TpaySignature::cents($this->money($paid)) === TpaySignature::cents(self::AMOUNT);
    }

    /** @param array<string, mixed> $fields */
    public function assertNotification(string $rawBody, ?string $jws, array $fields): void
    {
        if (!$this->configured()) {
            throw new TpayTemporaryException('Tpay nie jest skonfigurowany.');
        }
        if (!is_string($jws) || $jws === '') {
            throw new TpayRejectedException('Brak podpisu Tpay.');
        }
        $x5u = TpaySignature::certificateUrl($jws);
        if (!is_string($x5u) || !TpaySignature::certificateUrlAllowed($x5u, $this->secureOrigin())) {
            throw new TpayRejectedException('Niedozwolony certyfikat podpisu Tpay.');
        }
        try {
            $certificate = $this->fetch($x5u);
            $root = $this->fetch($this->secureOrigin().'/x509/tpay-jws-root.pem');
        } catch (TpayTemporaryException $error) {
            throw $error;
        }
        if (!TpaySignature::valid($rawBody, $jws, $certificate, $root)) {
            throw new TpayRejectedException('Nieprawidłowy podpis Tpay.');
        }
        $merchantId = $this->stringField($fields, 'id');
        $title = $this->stringField($fields, 'tr_id');
        $amount = $this->stringField($fields, 'tr_amount');
        $paid = $this->stringField($fields, 'tr_paid');
        $crc = $this->stringField($fields, 'tr_crc');
        $status = $this->stringField($fields, 'tr_status');
        $postedSum = strtolower($this->stringField($fields, 'md5sum'));
        $testMode = $this->stringField($fields, 'test_mode');
        $expected = TpaySignature::checksum($merchantId, $title, $amount, $crc, $this->securityCode);
        if (!hash_equals($expected, $postedSum) || !hash_equals($this->merchantId, $merchantId)) {
            throw new TpayRejectedException('Niezgodna suma kontrolna Tpay.');
        }
        $expectedTest = $this->sandbox() ? '1' : '0';
        if ($testMode !== $expectedTest) {
            throw new TpayRejectedException('Tryb testowy powiadomienia nie zgadza się z konfiguracją.');
        }
        if ($status === 'chargeback') {
            return;
        }
        if ($status !== 'true') {
            throw new TpayRejectedException('Powiadomienie nie jest opłaceniem.');
        }
        $cents = TpaySignature::cents(self::AMOUNT);
        if (TpaySignature::cents($amount) !== $cents || TpaySignature::cents($paid) !== $cents || !preg_match('/^[0-9a-f-]{36}$/Di', $crc)) {
            throw new TpayRejectedException('Kwota albo identyfikator płatności jest niezgodny.');
        }
    }

    public function paymentUrlAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'https' || !is_string($host)) {
            return false;
        }
        $allowed = $this->sandbox() ? 'secure.sandbox.tpay.com' : 'secure.tpay.com';

        return strtolower($host) === $allowed;
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $token = $this->accessToken();
        $payload = null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR);
        $raw = $this->fetch($this->apiOrigin().$path, [
            'method' => $method,
            'headers' => [
                'Authorization: Bearer '.$token,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            'body' => $payload,
        ]);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new TpayTemporaryException('Tpay zwrócił nieczytelną odpowiedź.');
        }

        return $decoded;
    }

    private function accessToken(): string
    {
        if (self::$token !== null && self::$tokenUntil > time() + 30) {
            return self::$token;
        }
        $raw = $this->fetch($this->apiOrigin().'/oauth/auth', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            'body' => http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
        ]);
        $decoded = json_decode($raw, true);
        $token = is_array($decoded) ? ($decoded['access_token'] ?? '') : '';
        $expires = is_array($decoded) ? (int) ($decoded['expires_in'] ?? 0) : 0;
        if (!is_string($token) || $token === '') {
            $this->logger->error('Tpay OAuth failed.');
            throw new TpayTemporaryException('Nie udało się połączyć z Tpay.');
        }
        self::$token = $token;
        self::$tokenUntil = time() + max(60, $expires);

        return $token;
    }

    /** @param array{method?: string, headers?: list<string>, body?: string|null} $options */
    private function fetch(string $url, array $options = []): string
    {
        $handle = curl_init($url);
        if (false === $handle) {
            throw new TpayTemporaryException('Nie udało się połączyć z Tpay.');
        }
        $headers = $options['headers'] ?? [];
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
        ]);
        if (array_key_exists('body', $options) && null !== $options['body']) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $options['body']);
        }
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            $this->logger->error('Tpay HTTP failed.', ['status' => $status, 'host' => parse_url($url, PHP_URL_HOST), 'curl' => $curlError]);
            throw new TpayTemporaryException($this->publicFailure($status, is_string($raw) ? $raw : ''));
        }

        return $raw;
    }

    private function apiOrigin(): string
    {
        // api.sandbox.tpay.com does not exist. Sandbox Open API is on openapi.sandbox.tpay.com.
        return $this->sandbox() ? 'https://openapi.sandbox.tpay.com' : 'https://api.tpay.com';
    }

    private function secureOrigin(): string
    {
        return $this->sandbox() ? 'https://secure.sandbox.tpay.com' : 'https://secure.tpay.com';
    }

    /** @param array<string, mixed> $fields */
    private function stringField(array $fields, string $key): string
    {
        $value = $fields[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function publicFailure(int $status, string $raw): string
    {
        if ($status === 0) {
            return 'Nie udało się połączyć z Tpay.';
        }
        $decoded = json_decode($raw, true);
        $detail = '';
        if (is_array($decoded)) {
            if (isset($decoded['errors'][0]) && is_array($decoded['errors'][0])) {
                $detail = (string) ($decoded['errors'][0]['errorMessage'] ?? $decoded['errors'][0]['message'] ?? '');
            }
            if ($detail === '') {
                $detail = (string) ($decoded['error_description'] ?? $decoded['message'] ?? '');
            }
        }
        $detail = trim((string) preg_replace('/\s+/', ' ', $detail));
        if ($detail !== '' && strlen($detail) < 180 && !str_contains(strtolower($detail), 'secret')) {
            return 'Tpay odrzucił płatność: '.$detail;
        }

        return 'Tpay odrzucił kod BLIK. Wygeneruj nowy kod w banku i wpisz go jeszcze raz.';
    }

    private function money(mixed $amount): string
    {
        if (is_int($amount) || is_float($amount)) {
            return number_format((float) $amount, 2, '.', '');
        }

        return is_string($amount) ? $amount : '';
    }
}
