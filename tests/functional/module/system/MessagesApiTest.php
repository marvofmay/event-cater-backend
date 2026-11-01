<?php

declare(strict_types=1);

namespace App\tests\functional\module\system;

use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Entity\User;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\RoleAccess;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class MessagesApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path = '', array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, '/api/messages'.$path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function account(string $email, bool $admin = true): string
    {
        $manager = self::getContainer()->get('doctrine')->getManager();
        $user = User::create($email);
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        if ($admin) $user->makeTenantAdministrator();
        $manager->persist($user);
        $manager->flush();
        return $user->getUUID()->toString();
    }

    public function testSendingReadingPrivacyAndIndependentUnreadCounts(): void
    {
        $this->getAuthenticatedClient();
        $first = $this->account('first-recipient@example.test');
        $second = $this->account('second-recipient@example.test');
        $this->account('unrelated@example.test');
        $options = $this->api('GET', '/recipients?q=first-recipient');
        self::assertSame($first, $options['items'][0]['uuid']);
        $message = $this->api('POST', body: ['title' => 'Spotkanie', 'content' => "Jutro o 10:00.\nSala główna.", 'recipientUuids' => [$first, $second]], status: 201);
        self::assertSame(1, $this->api('GET', '?folder=sent')['total']);
        self::assertSame(0, $this->api('GET', '/unread-count')['unreadCount']);
        $sent = $this->api('GET', '/'.$message['uuid']);
        self::assertTrue($sent['isSender']);
        self::assertCount(2, $sent['recipients']);
        $this->api('PATCH', '/'.$message['uuid'].'/read', status: 404);
        $this->getAuthenticatedClient('first-recipient@example.test', 'TestOnly123!');
        self::assertSame(1, $this->api('GET', '/unread-count')['unreadCount']);
        self::assertSame(1, $this->api('GET', '?folder=inbox&unread=true')['total']);
        $received = $this->api('GET', '/'.$message['uuid']);
        self::assertFalse($received['isSender']);
        self::assertTrue($received['isRecipient']);
        self::assertCount(1, $received['recipients']);
        self::assertSame($first, $received['recipients'][0]['uuid']);
        self::assertNull($received['readAt']);
        $read = $this->api('PATCH', '/'.$message['uuid'].'/read');
        self::assertNotNull($read['readAt']);
        self::assertSame($read['readAt'], $this->api('PATCH', '/'.$message['uuid'].'/read')['readAt']);
        self::assertSame(0, $this->api('GET', '/unread-count')['unreadCount']);
        self::assertSame(0, $this->api('GET', '?unread=true')['total']);
        $this->getAuthenticatedClient('second-recipient@example.test', 'TestOnly123!');
        self::assertSame(1, $this->api('GET', '/unread-count')['unreadCount']);
        self::assertSame(0, $this->api('GET', '?folder=sent')['total']);
        $this->getAuthenticatedClient('unrelated@example.test', 'TestOnly123!');
        $this->api('GET', '/'.$message['uuid'], status: 404);
        $this->api('PATCH', '/'.$message['uuid'].'/read', status: 404);
        self::assertSame(0, $this->api('GET')['total']);
        $this->getAuthenticatedClient();
        $sent = $this->api('GET', '/'.$message['uuid']);
        self::assertNotNull($sent['recipients'][0]['readAt']);
        self::assertNull($sent['recipients'][1]['readAt']);
    }

    public function testRetriesAreIdempotentAndValidationIsAtomic(): void
    {
        $this->getAuthenticatedClient();
        $recipient = $this->account('recipient@example.test');
        $body = ['requestUuid' => Uuid::uuid4()->toString(), 'title' => 'Tytuł', 'content' => 'Treść', 'recipientUuids' => [$recipient, $recipient]];
        $first = $this->api('POST', body: $body, status: 201);
        self::assertSame($first, $this->api('POST', body: $body, status: 201));
        self::assertSame(1, $this->api('GET', '?folder=sent')['total']);
        $this->api('POST', body: [...$body, 'content' => 'Inna treść'], status: 409);
        foreach ([['recipientUuids' => []], ['recipientUuids' => [$recipient, Uuid::uuid4()->toString()]], ['recipientUuids' => [42]], ['recipientUuids' => array_fill(0, 101, $recipient)], ['title' => ' '], ['title' => str_repeat('x', 201)], ['content' => str_repeat('x', 10001)]] as $invalid) {
            $this->api('POST', body: array_replace($body, ['requestUuid' => Uuid::uuid4()->toString()], $invalid), status: 422);
        }
        self::assertSame(1, $this->api('GET', '?folder=sent')['total']);
        self::assertSame(1, $this->api('GET', '?folder=sent&q=Tytuł')['total']);
        self::assertSame(0, $this->api('GET', '?folder=sent&q=%25')['total']);
        $this->api('GET', '?folder=unknown', status: 422);
        $this->api('GET', '?folder=sent&unread=true', status: 422);
        $this->api('GET', '?limit=101', status: 422);
    }

    public function testPermissionsAndUnavailableAccountsAreEnforced(): void
    {
        $this->api('GET', status: 401);
        $this->getAuthenticatedClient();
        $unavailable = $this->account('no-access@example.test', false);
        $deleted = $this->account('deleted-account@example.test');
        self::getContainer()->get('doctrine')->getConnection()->executeStatement('UPDATE user SET deleted_at = NOW() WHERE uuid = ?', [$deleted]);
        foreach ([$unavailable, $deleted] as $uuid) {
            $this->api('POST', body: ['title' => 'Tytuł', 'content' => 'Treść', 'recipientUuids' => [$uuid]], status: 422);
        }
        self::assertSame([], $this->api('GET', '/recipients?q=no-access')['items']);
        $this->getAuthenticatedClient('no-access@example.test', 'TestOnly123!');
        $this->api('GET', status: 403);
        $this->api('GET', '/unread-count', status: 403);
        $this->api('GET', '/recipients', status: 403);
        $this->api('POST', body: ['title' => 'Tytuł', 'content' => 'Treść', 'recipientUuids' => [$unavailable]], status: 403);
    }

    public function testMigrationAddsReadPermissionsButDoesNotGrantSending(): void
    {
        $manager = self::getContainer()->get('doctrine')->getManager();
        $role = Role::create('Rola odbiorcy');
        $access = $manager->getRepository(Access::class)->findOneBy(['name' => 'messages']);
        $manager->persist($role);
        $manager->persist(new RoleAccess($role, $access));
        $manager->flush();
        $db = $manager->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101110000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261101110000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement());
        }
        $permissions = $db->fetchFirstColumn('SELECT permission.name FROM role_access_permission rap JOIN permission ON permission.uuid = rap.permission_uuid WHERE rap.role_uuid = ? AND rap.access_uuid = ? AND rap.deleted_at IS NULL ORDER BY permission.name', [$role->getUUID()->toString(), $access->getUUID()->toString()]);
        self::assertSame(['list', 'view'], $permissions);
    }

    public function testReplyIsRestrictedToTheOriginalSenderAndOwnMailboxDeletionIsIndependent(): void
    {
        $this->getAuthenticatedClient();
        $first = $this->account('reply-first@example.test');
        $second = $this->account('reply-second@example.test');
        $this->account('reply-stranger@example.test');
        $original = $this->api('POST', body: ['title' => 'Plan', 'content' => 'Pierwotna treść', 'recipientUuids' => [$first, $second]], status: 201);
        $sender = $this->api('GET', '/'.$original['uuid'])['senderUuid'];
        $this->getAuthenticatedClient('reply-first@example.test', 'TestOnly123!');
        $reply = ['requestUuid' => Uuid::uuid4()->toString(), 'title' => 'Re: Plan', 'content' => 'Potwierdzam', 'recipientUuids' => [$sender], 'replyToUuid' => $original['uuid']];
        $sent = $this->api('POST', body: $reply, status: 201);
        self::assertSame($sent, $this->api('POST', body: $reply, status: 201));
        self::assertSame($original['uuid'], $this->api('GET', '/'.$sent['uuid'])['replyToUuid']);
        $this->api('POST', body: [...$reply, 'requestUuid' => Uuid::uuid4()->toString(), 'recipientUuids' => [$second]], status: 422);
        $this->api('DELETE', '/'.$original['uuid'].'?folder=inbox');
        self::assertSame(0, $this->api('GET', '/unread-count')['unreadCount']);
        $this->api('GET', '/'.$original['uuid'], status: 404);
        $this->api('PATCH', '/'.$original['uuid'].'/read', status: 404);
        $this->api('DELETE', '/'.$original['uuid'].'?folder=inbox');
        $this->getAuthenticatedClient('reply-second@example.test', 'TestOnly123!');
        self::assertSame(1, $this->api('GET', '/unread-count')['unreadCount']);
        self::assertSame('Pierwotna treść', $this->api('GET', '/'.$original['uuid'])['content']);
        $this->getAuthenticatedClient('reply-stranger@example.test', 'TestOnly123!');
        $this->api('POST', body: [...$reply, 'requestUuid' => Uuid::uuid4()->toString()], status: 404);
        $this->getAuthenticatedClient();
        self::assertSame(1, $this->api('GET', '/unread-count')['unreadCount']);
        $this->api('DELETE', '/'.$original['uuid'].'?folder=sent');
        $this->api('GET', '/'.$original['uuid'], status: 404);
        self::assertSame(0, $this->api('GET', '?folder=sent')['total']);
        $this->getAuthenticatedClient('reply-second@example.test', 'TestOnly123!');
        self::assertSame('Pierwotna treść', $this->api('GET', '/'.$original['uuid'])['content']);
    }

    public function testBulkDeletionIsAtomicScopedAndSortingIsValidated(): void
    {
        $this->getAuthenticatedClient();
        $recipient = $this->account('bulk-recipient@example.test');
        $first = $this->api('POST', body: ['title' => 'Beta', 'content' => 'Treść', 'recipientUuids' => [$recipient]], status: 201);
        $second = $this->api('POST', body: ['title' => 'Alfa', 'content' => 'Treść', 'recipientUuids' => [$recipient]], status: 201);
        self::assertSame(['Alfa', 'Beta'], array_column($this->api('GET', '?folder=sent&sortBy=title&sortDirection=asc')['items'], 'title'));
        $this->api('GET', '?sortBy=unknown', status: 422);
        $this->api('POST', '/delete', ['folder' => 'sent', 'uuids' => [$first['uuid'], Uuid::uuid4()->toString()]], 404);
        self::assertSame(2, $this->api('GET', '?folder=sent')['total']);
        $this->api('POST', '/delete', ['folder' => 'sent', 'uuids' => []], 422);
        $this->api('DELETE', '/'.$first['uuid'].'?folder=inbox', status: 404);
        $this->getAuthenticatedClient('bulk-recipient@example.test', 'TestOnly123!');
        $this->api('POST', '/delete', ['folder' => 'inbox', 'uuids' => [$first['uuid'], $second['uuid']]]);
        self::assertSame(0, $this->api('GET')['total']);
        self::assertSame(0, $this->api('GET', '/unread-count')['unreadCount']);
        $this->getAuthenticatedClient();
        self::assertSame(2, $this->api('GET', '?folder=sent')['total']);
    }

    public function testReplyAndDeletionMigrationPreservesExistingMessages(): void
    {
        $this->getAuthenticatedClient();
        $recipient = $this->account('migration-recipient@example.test');
        $sent = $this->api('POST', body: ['title' => 'Zachowany tytuł', 'content' => 'Zachowana treść', 'recipientUuids' => [$recipient]], status: 201);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101120000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261101120000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement());
        }
        $message = $this->api('GET', '/'.$sent['uuid']);
        self::assertSame('Zachowana treść', $message['content']);
        self::assertNull($message['replyToUuid']);
        self::assertCount(1, $message['recipients']);
    }
}
