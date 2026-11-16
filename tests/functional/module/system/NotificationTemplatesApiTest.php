<?php

declare(strict_types=1);

namespace App\tests\functional\module\system;

use App\Module\Company\Domain\Entity\User;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use App\Module\System\Notification\Domain\Service\Message\NotificationResolver;
use App\tests\functional\FunctionalTestBase;

final class NotificationTemplatesApiTest extends FunctionalTestBase
{
    private function api(string $method, array $body = [], int $status = 200, string $query = ''): array
    {
        $this->client->jsonRequest($method, '/api/settings/notification-templates'.$query, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testListFiltersPaginationAndTemplateRendering(): void
    {
        $this->getAuthenticatedClient();
        $page = $this->api('GET', query: '?pageSize=100')['data'];
        self::assertSame(126, $page['total']);
        self::assertCount(100, $page['items']);
        self::assertArrayHasKey('uuid', $page['items'][0]);
        self::assertSame($page['items'][0]['eventName'], $page['items'][0]['eventNane']);
        $filtered = $this->api('GET', query: '?eventName=EmployeeImportedEvent&channelCode=internal&isDefault=true')['data'];
        self::assertCount(1, $filtered['items']);
        self::assertTrue($filtered['items'][0]['isDefault']);
        self::assertSame('EmployeeImportedEvent', $filtered['items'][0]['eventName']);
        self::assertSame('internal', $filtered['items'][0]['channelCode']);
        self::assertSame(6, $this->api('GET', query: '?phrase=EmployeeImportedEvent')['data']['total']);
        self::assertSame(63, $this->api('GET', query: '?isActive=false')['data']['total']);
        self::assertSame(63, $this->api('GET', query: '?isDefault=false')['data']['total']);
        $first = $this->api('GET', query: '?pageSize=2&page=1&sortBy=eventName&sortDirection=asc')['data'];
        $second = $this->api('GET', query: '?pageSize=2&page=2&sortBy=eventName&sortDirection=asc')['data'];
        self::assertCount(2, $first['items']);
        self::assertNotSame($first['items'][0]['uuid'], $second['items'][0]['uuid']);
        $this->api('GET', status: 422, query: '?pageSize=501');
        $this->api('GET', status: 422, query: '?sortBy=unknown');
        $rendered = self::getContainer()->get(NotificationResolver::class)->renderTemplate('Import {{importKind}}: {{importStatus}}', ['importKind' => 'pracowników', 'importStatus' => 'zakończony']);
        self::assertSame('Import pracowników: zakończony', $rendered);
    }

    public function testEditingTitleContentAndActivationDoesNotDuplicateChannelMessages(): void
    {
        $this->getAuthenticatedClient();
        $body = ['eventName' => 'EmployeeImportedEvent', 'channelCode' => 'internal', 'title' => 'NOWY TYTUŁ {{importKind}}', 'content' => 'Wynik: {{importStatus}}', 'searchDefault' => false, 'markAsActive' => true];
        $this->api('PUT', $body, 201);
        $items = $this->api('GET', query: '?eventName=EmployeeImportedEvent&channelCode=internal')['data']['items'];
        self::assertCount(1, array_filter($items, static fn (array $item) => $item['isActive']));
        $custom = array_values(array_filter($items, static fn (array $item) => !$item['isDefault']))[0];
        self::assertSame($body['title'], $custom['title']);
        self::assertSame($body['content'], $custom['content']);
        self::assertTrue($custom['isActive']);
        $this->api('PUT', [...$body, 'title' => 'nowy tytuł {{importKind}}'], 201);
        $custom = $this->api('GET', query: '?eventName=EmployeeImportedEvent&channelCode=internal&isDefault=false')['data']['items'][0];
        self::assertSame('nowy tytuł {{importKind}}', $custom['title']);
        $reader = self::getContainer()->get(NotificationTemplateSettingReaderInterface::class);
        $internal = array_filter($reader->getActiveByEventName('EmployeeImportedEvent')->toArray(), static fn ($template) => $template->getChannel()->getChannelCode() === 'internal');
        self::assertCount(1, $internal);
        $this->api('PUT', [...$body, 'markAsActive' => false], 201);
        self::assertSame(0, $this->api('GET', query: '?eventName=EmployeeImportedEvent&channelCode=internal&isActive=true')['data']['total']);
        $this->api('PUT', [...$body, 'searchDefault' => true], 201);
        self::assertSame(1, $this->api('GET', query: '?eventName=EmployeeImportedEvent&channelCode=internal&isActive=true')['data']['total']);
        $this->api('PUT', [...$body, 'title' => ''], 422);
        $this->api('PUT', [...$body, 'content' => str_repeat('x', 501)], 422);
        $this->api('PUT', [...$body, 'eventName' => 'MissingEvent'], 404);
        $this->api('PUT', [...$body, 'channelCode' => 'missing'], 404);
    }

    public function testTemplateSettingsRequirePermissions(): void
    {
        $this->getAuthenticatedClient();
        $user = User::create('template-no-access@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('template-no-access@example.test', 'TestOnly123!');
        $this->api('GET', status: 403);
        $this->api('PUT', ['eventName' => 'EmployeeImportedEvent', 'channelCode' => 'internal', 'title' => 'Tytuł', 'content' => 'Treść'], 403);
    }
}
