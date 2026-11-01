<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Preference;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class PreferenceService
{
    public const array RESOURCES = ['companies', 'departments', 'employees', 'positions', 'roles', 'contractTypes', 'industries', 'notifications'];
    public const array PAGE_SIZES = [5, 10, 25, 50, 100];
    public const array CHANNELS = ['sms', 'email', 'internal'];

    public function __construct(private Connection $connection, private PreferenceSecrets $secrets)
    {
    }

    public function row(string $owner, string $resource): array
    {
        $row = $this->connection->fetchAssociative('SELECT payload, version FROM application_preference WHERE owner = ? AND resource = ?', [$owner, $resource]);

        return $row ? ['payload' => json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR), 'version' => (int) $row['version']] : ['payload' => [], 'version' => 0];
    }

    public function masterChannels(): array
    {
        $channels = array_fill_keys(self::CHANNELS, false);
        foreach ($this->connection->fetchAllAssociative('SELECT channel_code, enabled FROM notification_channel_setting WHERE deleted_at IS NULL') as $row) {
            if (array_key_exists($row['channel_code'], $channels)) {
                $channels[$row['channel_code']] = (bool) $row['enabled'];
            }
        }

        return $channels;
    }

    public function channelEnabled(string $channel, string $userUuid): bool
    {
        $master = $this->masterChannels();
        $own = $this->row($userUuid, 'notifications')['payload']['channels'] ?? [];

        return ($master[$channel] ?? false) && ($own[$channel] ?? true);
    }

    public function filterRecipients(string $channel, array $users): array
    {
        if (!($this->masterChannels()[$channel] ?? false) || [] === $users) {
            return [];
        }
        $overrides = $this->connection->fetchAllAssociative("SELECT owner, payload FROM application_preference WHERE resource = 'notifications' AND owner IN (?)", [array_map(static fn ($user): string => $user->getUUID()->toString(), $users)], [\Doctrine\DBAL\ArrayParameterType::STRING]);
        $disabled = [];
        foreach ($overrides as $row) {
            if (false === (json_decode($row['payload'], true)['channels'][$channel] ?? null)) {
                $disabled[$row['owner']] = true;
            }
        }

        return array_values(array_filter($users, static fn ($user): bool => !isset($disabled[$user->getUUID()->toString()])));
    }

    public function pageSizes(string $userUuid): array
    {
        $result = array_fill_keys(self::RESOURCES, 5);
        $rows = $this->connection->fetchAllAssociative('SELECT owner, resource, payload FROM application_preference WHERE owner IN (?, ?)', ['tenant', $userUuid]);
        foreach (['tenant', $userUuid] as $owner) {
            foreach ($rows as $row) {
                $size = json_decode($row['payload'], true)['pageSize'] ?? null;
                if ($row['owner'] === $owner && array_key_exists($row['resource'], $result) && in_array($size, self::PAGE_SIZES, true)) {
                    $result[$row['resource']] = $size;
                }
            }
        }

        return $result;
    }

    public function snapshot(string $resource, string $userUuid, bool $administrator): array
    {
        $master = $this->row('tenant', $resource);
        $own = $this->row($userUuid, $resource);
        $tenant = ['pageSize' => $master['payload']['pageSize'] ?? 5];
        $user = ['pageSize' => $own['payload']['pageSize'] ?? null];
        $effective = ['pageSize' => $user['pageSize'] ?? $tenant['pageSize']];
        if ('notifications' === $resource) {
            $tenant['channels'] = $this->masterChannels();
            $user['channels'] = array_replace(array_fill_keys(self::CHANNELS, null), $own['payload']['channels'] ?? []);
            $effective['channels'] = [];
            foreach (self::CHANNELS as $channel) {
                $effective['channels'][$channel] = $tenant['channels'][$channel] && ($user['channels'][$channel] ?? true);
            }
            if ($administrator) {
                $sms = $master['payload']['sms'] ?? [];
                $used = $this->smsUsed($sms['quotaMode'] ?? 'monthly');
                $tenant['sms'] = ['hasApiKey' => !empty($sms['apiKey']), 'sender' => $sms['sender'] ?? '', 'quotaMode' => $sms['quotaMode'] ?? 'monthly', 'limit' => $sms['limit'] ?? 0, 'used' => $used, 'remaining' => max(0, ($sms['limit'] ?? 0) - $used), 'period' => self::period()];
            }
        }

        return ['tenant' => $tenant, 'user' => $user, 'effective' => $effective, 'tenantVersion' => $master['version'], 'userVersion' => $own['version'], 'canManageTenant' => $administrator];
    }

    public static function period(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw')))->format('Y-m');
    }

    public function smsUsed(string $mode): int
    {
        return (int) $this->connection->fetchOne("SELECT COALESCE(SUM(units), 0) FROM sms_delivery WHERE status IN ('reserved', 'sent', 'unknown')".('monthly' === $mode ? ' AND period = ?' : ''), 'monthly' === $mode ? [self::period()] : []);
    }

    public function save(string $resource, string $owner, #[\SensitiveParameter] array $input): void
    {
        $allowed = ['scope', 'version', 'pageSize', ...('notifications' === $resource ? ['channels', ...('tenant' === $owner ? ['sms'] : [])] : [])];
        if (array_diff(array_keys($input), $allowed) || !isset($input['version']) || !is_int($input['version']) || !array_key_exists('pageSize', $input)
            || (!in_array($input['pageSize'], self::PAGE_SIZES, true) && !(null === $input['pageSize'] && 'tenant' !== $owner))) {
            throw new UnprocessableEntityHttpException('Nieprawidłowe ustawienia lub liczba rekordów na stronę.');
        }
        $this->connection->transactional(function () use ($resource, $owner, $input): void {
            $raw = $this->connection->fetchAssociative('SELECT payload, version FROM application_preference WHERE owner = ? AND resource = ? FOR UPDATE', [$owner, $resource]);
            if ((int) ($raw['version'] ?? 0) !== $input['version']) {
                throw new ConflictHttpException('Ustawienia zmieniono w innej karcie. Odśwież formularz.');
            }
            $payload = $raw ? json_decode($raw['payload'], true, flags: JSON_THROW_ON_ERROR) : [];
            $payload['pageSize'] = $input['pageSize'];
            if (array_key_exists('channels', $input)) {
                if ('notifications' !== $resource || !is_array($input['channels']) || array_diff(array_keys($input['channels']), self::CHANNELS)) {
                    throw new UnprocessableEntityHttpException('Nieprawidłowe kanały powiadomień.');
                }
                foreach ($input['channels'] as $enabled) {
                    if (!is_bool($enabled) && !(null === $enabled && 'tenant' !== $owner)) {
                        throw new UnprocessableEntityHttpException('Nieprawidłowa wartość kanału.');
                    }
                }
                $payload['channels'] = array_replace($payload['channels'] ?? [], $input['channels']);
            }
            if (isset($input['sms'])) {
                $sms = $input['sms'];
                if (!is_array($sms) || array_diff(array_keys($sms), ['sender', 'limit', 'quotaMode', 'apiKey', 'clearApiKey'])
                    || !is_string($sms['sender'] ?? null) || !preg_match('/^[A-Za-z0-9]{0,11}$/D', $sms['sender'])
                    || !is_int($sms['limit'] ?? null) || $sms['limit'] < 0 || $sms['limit'] > 10000000
                    || !in_array($sms['quotaMode'] ?? null, ['monthly', 'pool'], true)
                    || (isset($sms['apiKey']) && (!is_string($sms['apiKey']) || strlen($sms['apiKey']) > 1024))
                    || (isset($sms['clearApiKey']) && !is_bool($sms['clearApiKey']))) {
                    throw new UnprocessableEntityHttpException('Nieprawidłowa konfiguracja Brevo lub puli SMS. Nadawca: maksymalnie 11 liter/cyfr.');
                }
                $encrypted = $payload['sms']['apiKey'] ?? null;
                if (!empty($sms['clearApiKey'])) {
                    $encrypted = null;
                } elseif (!empty(trim($sms['apiKey'] ?? ''))) {
                    $encrypted = $this->secrets->encrypt(trim($sms['apiKey']));
                }
                $payload['sms'] = ['sender' => $sms['sender'], 'limit' => $sms['limit'], 'quotaMode' => $sms['quotaMode'], 'apiKey' => $encrypted];
            }
            if ('tenant' === $owner && 'notifications' === $resource) {
                $channels = array_replace($this->masterChannels(), $input['channels'] ?? []);
                if ($channels['sms'] && (empty($payload['sms']['apiKey']) || empty($payload['sms']['sender']) || empty($payload['sms']['limit']))) {
                    throw new UnprocessableEntityHttpException('Włączenie SMS wymaga klucza Brevo, nadawcy i dodatniej puli.');
                }
                foreach ($channels as $channel => $enabled) {
                    $this->connection->executeStatement('INSERT INTO notification_channel_setting (channel_code, enabled, created_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE enabled = ?, deleted_at = NULL', [$channel, (int) $enabled, (int) $enabled]);
                }
            }
            if ($raw) {
                $this->connection->executeStatement('UPDATE application_preference SET payload = ?, version = version + 1 WHERE owner = ? AND resource = ?', [json_encode($payload, JSON_THROW_ON_ERROR), $owner, $resource]);
            } else {
                $this->connection->insert('application_preference', ['owner' => $owner, 'resource' => $resource, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'version' => 1]);
            }
        });
    }
}
