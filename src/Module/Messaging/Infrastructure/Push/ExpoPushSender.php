<?php

declare(strict_types=1);

namespace App\Module\Messaging\Infrastructure\Push;

use App\Module\Messaging\Domain\Repository\PushTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExpoPushSender
{
    private const string ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function __construct(
        private HttpClientInterface $httpClient,
        private PushTokenRepository $tokens,
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private string $appEnv,
    ) {
    }

    /**
     * @param list<string> $pushTokens
     */
    public function send(
        array $pushTokens,
        string $title,
        string $body,
        array $data,
        string $channelId = 'messages',
    ): void {
        if ($this->appEnv === 'test') {
            return;
        }

        $pushTokens = array_values(array_unique(array_filter($pushTokens)));
        if ($pushTokens === []) {
            return;
        }

        $messages = array_map(
            static fn (string $token): array => [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'channelId' => $channelId,
                'priority' => 'high',
            ],
            $pushTokens,
        );

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $messages,
                'timeout' => 15,
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable $error) {
            $this->logger->warning('Expo push request failed: '.$error->getMessage());

            return;
        }

        $tickets = $payload['data'] ?? [];
        if (!is_array($tickets)) {
            return;
        }

        foreach ($tickets as $index => $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $status = $ticket['status'] ?? null;
            if ($status === 'ok') {
                continue;
            }
            $details = $ticket['details'] ?? [];
            $error = is_array($details) ? ($details['error'] ?? null) : null;
            if (in_array($error, ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                $token = $pushTokens[$index] ?? null;
                if (is_string($token)) {
                    $this->tokens->removeByToken($token);
                }
            }
            $this->logger->info('Expo push ticket error', ['ticket' => $ticket]);
        }
    }
}
