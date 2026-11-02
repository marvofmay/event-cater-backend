<?php

declare(strict_types=1);

namespace App\Module\Facebook\Infrastructure;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FacebookGraphClient
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(FACEBOOK_APP_ID)%')] private string $appId,
        #[Autowire('%env(FACEBOOK_APP_SECRET)%')] private string $appSecret,
        #[Autowire('%env(FACEBOOK_REDIRECT_URI)%')] private string $redirectUri,
        #[Autowire('%env(default:facebook_graph_version_default:FACEBOOK_GRAPH_VERSION)%')] private string $version,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->appSecret !== '' && $this->redirectUri !== '';
    }

    public function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new BusinessRuleViolation('Integracja Facebook nie jest skonfigurowana. Skontaktuj się z administratorem systemu.');
        }
    }

    public function oauthUrl(string $state): string
    {
        $this->assertConfigured();
        $query = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            // business_management: bez tego /me/accounts bywa puste dla stron w Meta Business Suite
            'scope' => 'pages_show_list,pages_manage_posts,pages_read_engagement,business_management',
            'response_type' => 'code',
        ]);

        return 'https://www.facebook.com/'.$this->version.'/dialog/oauth?'.$query;
    }

    public function exchangeCode(string $code): string
    {
        $this->assertConfigured();
        $data = $this->get('oauth/access_token', [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);
        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new BusinessRuleViolation('Facebook nie zwrócił tokenu użytkownika.');
        }

        return $token;
    }

    public function longLivedUserToken(string $shortLivedToken): string
    {
        $this->assertConfigured();
        $data = $this->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);
        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new BusinessRuleViolation('Nie udało się uzyskać długoterminowego tokenu Facebook.');
        }

        return $token;
    }

    /** @return list<array{id: string, name: string, access_token: string}> */
    public function pages(string $userToken): array
    {
        $items = $this->mapPages($this->get('me/accounts', [
            'fields' => 'id,name,access_token',
            'access_token' => $userToken,
            'limit' => 100,
        ]));
        if ($items !== []) {
            return $items;
        }

        // Strony przypisane przez Business Suite / role task-based
        return $this->mapPages($this->get('me/assigned_pages', [
            'fields' => 'id,name,access_token',
            'access_token' => $userToken,
            'limit' => 100,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{id: string, name: string, access_token: string}>
     */
    private function mapPages(array $data): array
    {
        $items = [];
        foreach ($data['data'] ?? [] as $page) {
            if (!is_array($page) || !isset($page['id'], $page['name'], $page['access_token'])) {
                continue;
            }
            $items[] = [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
                'access_token' => (string) $page['access_token'],
            ];
        }

        return $items;
    }

    public function publishFeed(string $pageId, string $pageToken, string $message, array $attachedMedia = []): string
    {
        $payload = [
            'message' => $message,
            'access_token' => $pageToken,
        ];
        foreach (array_values($attachedMedia) as $index => $mediaFbid) {
            $payload['attached_media['.$index.']'] = json_encode(['media_fbid' => $mediaFbid], \JSON_THROW_ON_ERROR);
        }
        $data = $this->post($pageId.'/feed', $payload);
        $id = $data['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new BusinessRuleViolation('Facebook nie zwrócił identyfikatora posta.');
        }

        return $id;
    }

    public function publishPhoto(string $pageId, string $pageToken, string $absolutePath, string $caption, bool $published = true): string
    {
        if (!is_file($absolutePath)) {
            throw new BusinessRuleViolation('Nie znaleziono pliku zdjęcia do publikacji.');
        }
        $data = $this->http->request('POST', $this->url($pageId.'/photos'), [
            'body' => [
                'caption' => $caption,
                'published' => $published ? 'true' : 'false',
                'access_token' => $pageToken,
                'source' => fopen($absolutePath, 'r'),
            ],
        ])->toArray(false);
        $this->assertGraphOk($data);
        $id = $data['id'] ?? $data['post_id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new BusinessRuleViolation('Facebook nie zwrócił identyfikatora zdjęcia.');
        }

        return $id;
    }

    /** @param array<string, scalar> $query */
    private function get(string $path, array $query): array
    {
        $data = $this->http->request('GET', $this->url($path), ['query' => $query])->toArray(false);
        $this->assertGraphOk($data);

        return $data;
    }

    /** @param array<string, scalar|string> $body */
    private function post(string $path, array $body): array
    {
        $data = $this->http->request('POST', $this->url($path), ['body' => $body])->toArray(false);
        $this->assertGraphOk($data);

        return $data;
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->version.'/'.ltrim($path, '/');
    }

    private function assertGraphOk(array $data): void
    {
        if (!isset($data['error'])) {
            return;
        }
        $message = is_array($data['error']) ? (string) ($data['error']['message'] ?? 'Błąd Facebook Graph API.') : 'Błąd Facebook Graph API.';
        throw new BusinessRuleViolation($message);
    }
}
