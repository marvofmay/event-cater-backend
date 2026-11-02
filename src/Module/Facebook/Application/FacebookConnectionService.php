<?php

declare(strict_types=1);

namespace App\Module\Facebook\Application;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Facebook\Domain\Entity\FacebookPageConnection;
use App\Module\Facebook\Infrastructure\FacebookGraphClient;
use App\Module\Facebook\Infrastructure\FacebookTokenCipher;
use App\Module\Facebook\Infrastructure\Persistence\DoctrineFacebookPageConnectionRepository;

final readonly class FacebookConnectionService
{
    public function __construct(
        private FacebookGraphClient $graph,
        private FacebookTokenCipher $cipher,
        private DoctrineFacebookPageConnectionRepository $connections,
        private FacebookOAuthState $state,
    ) {
    }

    public function status(): array
    {
        $connection = $this->connections->get();

        return $connection?->toPublicArray() ?? ['connected' => false, 'pageId' => null, 'pageName' => null, 'connectedAt' => null];
    }

    public function connectUrl(string $userUuid): array
    {
        return [
            'url' => $this->graph->oauthUrl($this->state->issue($userUuid)),
            'configured' => $this->graph->isConfigured(),
        ];
    }

    /** @return array{connected?: bool, pages?: list<array{id: string, name: string}>, pageName?: string} */
    public function completeOAuth(string $code, string $state, string $userUuid, ?string $pageId = null): array
    {
        $this->state->assertValid($state, $userUuid);
        $short = $this->graph->exchangeCode($code);
        $long = $this->graph->longLivedUserToken($short);
        $pages = $this->graph->pages($long);
        if ($pages === []) {
            throw new BusinessRuleViolation('Nie znaleziono stron Facebook powiązanych z tym kontem. Upewnij się, że masz uprawnienia administratora strony firmowej.');
        }
        if ($pageId === null) {
            if (count($pages) === 1) {
                $this->persist($pages[0], $userUuid);

                return ['connected' => true, 'pageName' => $pages[0]['name']];
            }

            return [
                'connected' => false,
                'pages' => array_map(static fn (array $page) => ['id' => $page['id'], 'name' => $page['name']], $pages),
                'pendingToken' => $this->cipher->encrypt(json_encode([
                    'pages' => $pages,
                    'user' => $userUuid,
                    'exp' => time() + 600,
                ], \JSON_THROW_ON_ERROR)),
            ];
        }
        foreach ($pages as $page) {
            if ($page['id'] === $pageId) {
                $this->persist($page, $userUuid);

                return ['connected' => true, 'pageName' => $page['name']];
            }
        }
        throw new BusinessRuleViolation('Wybrana strona Facebook nie jest dostępna dla tego konta.');
    }

    public function selectPendingPage(string $pendingToken, string $pageId, string $userUuid): array
    {
        $payload = json_decode($this->cipher->decrypt($pendingToken), true);
        if (!is_array($payload) || ($payload['user'] ?? null) !== $userUuid || (int) ($payload['exp'] ?? 0) < time() || !is_array($payload['pages'] ?? null)) {
            throw new BusinessRuleViolation('Sesja wyboru strony Facebook wygasła. Połącz ponownie.');
        }
        foreach ($payload['pages'] as $page) {
            if (is_array($page) && ($page['id'] ?? null) === $pageId && isset($page['name'], $page['access_token'])) {
                $this->persist([
                    'id' => (string) $page['id'],
                    'name' => (string) $page['name'],
                    'access_token' => (string) $page['access_token'],
                ], $userUuid);

                return ['connected' => true, 'pageName' => (string) $page['name']];
            }
        }
        throw new BusinessRuleViolation('Wybrana strona Facebook nie jest dostępna.');
    }

    public function disconnect(): void
    {
        $this->connections->clear();
    }

    public function requireConnection(): FacebookPageConnection
    {
        $connection = $this->connections->get();
        if ($connection === null) {
            throw new BusinessRuleViolation('Najpierw połącz stronę firmową Facebook w ustawieniach (/settings/facebook).');
        }

        return $connection;
    }

    public function pageAccessToken(FacebookPageConnection $connection): string
    {
        return $this->cipher->decrypt($connection->encryptedToken());
    }

    /** @param array{id: string, name: string, access_token: string} $page */
    private function persist(array $page, string $userUuid): void
    {
        $connection = $this->connections->getOrCreate();
        $connection->replace($page['id'], $page['name'], $this->cipher->encrypt($page['access_token']), $userUuid);
        $this->connections->save($connection);
    }
}
