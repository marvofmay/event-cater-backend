<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Sms;

use App\Module\System\Infrastructure\Preference\PreferenceSecrets;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BrevoSmsSender
{
    public function __construct(private Connection $connection, private PreferenceService $preferences, private PreferenceSecrets $secrets, #[Autowire(service: 'app.brevo.http')] private HttpClientInterface $http, private LoggerInterface $logger)
    {
    }

    public function send(string $id, string $userUuid, string $recipient, string $content): void
    {
        $recipient = preg_replace('/[\s().-]/', '', $recipient);
        if (!$this->preferences->channelEnabled('sms', $userUuid) || !preg_match('/^\+?[1-9][0-9]{7,14}$/D', $recipient) || '' === trim($content) || mb_strlen($content) > 10000) {
            return;
        }
        $parameters = $this->connection->getParams();
        $parameters['dbname'] = $this->connection->fetchOne('SELECT DATABASE()');
        $ledger = DriverManager::getConnection($parameters);
        try {
            $segments = SmsSegments::count($content);
            $configuration = $ledger->transactional(function (Connection $db) use ($id, $userUuid, $segments): ?array {
                $raw = $db->fetchOne("SELECT payload FROM application_preference WHERE owner = 'tenant' AND resource = 'notifications' FOR UPDATE");
                if (!$raw || $db->fetchOne('SELECT id FROM sms_delivery WHERE id = ?', [$id])) {
                    return null;
                }
                $config = json_decode($raw, true, flags: JSON_THROW_ON_ERROR)['sms'] ?? [];
                if (empty($config['apiKey']) || empty($config['sender']) || empty($config['limit'])) {
                    return null;
                }
                $used = (int) $db->fetchOne("SELECT COALESCE(SUM(units), 0) FROM sms_delivery WHERE status IN ('reserved', 'sent', 'unknown')".('monthly' === $config['quotaMode'] ? ' AND period = ?' : ''), 'monthly' === $config['quotaMode'] ? [PreferenceService::period()] : []);
                if ($used + $segments['units'] > $config['limit']) {
                    $db->insert('sms_delivery', ['id' => $id, 'user_uuid' => $userUuid, 'period' => PreferenceService::period(), 'units' => 0, 'status' => 'quota_blocked']);

                    return null;
                }
                $db->insert('sms_delivery', ['id' => $id, 'user_uuid' => $userUuid, 'period' => PreferenceService::period(), 'units' => $segments['units'], 'status' => 'reserved']);

                return $config;
            });
            if (!$configuration) {
                return;
            }
            $status = 'unknown';
            $providerId = null;
            try {
                $response = $this->http->request('POST', 'https://api.brevo.com/v3/transactionalSMS/send', [
                    'headers' => ['api-key' => $this->secrets->decrypt($configuration['apiKey']), 'accept' => 'application/json'],
                    'json' => ['sender' => $configuration['sender'], 'recipient' => ltrim($recipient, '+'), 'content' => $content, 'type' => 'transactional', 'unicodeEnabled' => $segments['unicode']],
                    'timeout' => 10, 'max_duration' => 15,
                ]);
                $code = $response->getStatusCode();
                if (201 === $code) {
                    $status = 'sent';
                    $body = json_decode($response->getContent(false), true);
                    $providerId = isset($body['messageId']) ? substr((string) $body['messageId'], 0, 100) : null;
                } elseif ($code >= 400 && $code < 500) {
                    $status = 'rejected';
                }
            } catch (\Throwable) {
                $status = 'unknown';
            }
            $ledger->update('sms_delivery', ['status' => $status, 'provider_id' => $providerId], ['id' => $id]);
            if ('sent' !== $status) {
                $this->logger->warning('SMS delivery requires operator review.', ['deliveryId' => $id, 'status' => $status]);
            }
        } finally {
            $ledger->close();
        }
    }
}
