<?php

declare(strict_types=1);

namespace App\tests\functional\module\system;

use App\Module\Company\Domain\Entity\User;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Infrastructure\Preference\PreferenceSecrets;
use App\Module\System\Infrastructure\Sms\BrevoSmsSender;
use App\Module\System\Infrastructure\Sms\SmsSegments;
use App\tests\functional\FunctionalTestBase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PreferenceApiTest extends FunctionalTestBase
{
    private function api(string $method, string $resource, array $input = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, '/api/preferences'.$resource, $input);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'] ?? [];
    }

    public function testDashboardOrderIsPersonalValidatedVersionedAndDoesNotGrantAccess(): void
    {
        $this->getAuthenticatedClient();
        self::assertSame(['order' => [], 'version' => 0], $this->api('GET', '/dashboard'));
        $saved = $this->api('PUT', '/dashboard', ['version' => 0, 'order' => ['notes', 'company', 'events']]);
        self::assertSame(['notes', 'company', 'events'], $saved['order']);
        self::assertSame(1, $saved['version']);
        self::assertSame($saved['order'], $this->api('GET', '')['dashboardOrder']);
        $this->api('PUT', '/dashboard', ['version' => 0, 'order' => []], 409);
        foreach ([['order' => ['notes', 'notes']], ['order' => ['unknown']], ['order' => [42]], ['order' => 'notes'], ['order' => ['company' => 'notes']], ['version' => '1'], ['scope' => 'tenant'], ['owner' => 'tenant']] as $change) {
            $this->api('PUT', '/dashboard', array_replace(['version' => 1, 'order' => []], $change), 422);
        }
        $manager = self::getContainer()->get('doctrine')->getManager();
        $user = User::create('layout@example.test');
        $user->setPassword(password_hash('LayoutOnly123!', PASSWORD_BCRYPT));
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('layout@example.test', 'LayoutOnly123!');
        self::assertSame([], $this->api('GET', '/dashboard')['order']);
        $this->api('PUT', '/dashboard', ['version' => 0, 'order' => ['events']]);
        $this->client->request('GET', '/api/auth/authorization');
        self::assertResponseIsSuccessful();
        $authorization = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([], $authorization['accesses']);
        self::assertSame([], $authorization['permissions']);
        $this->getAuthenticatedClient();
        self::assertSame(['notes', 'company', 'events'], $this->api('GET', '/dashboard')['order']);
        $this->api('PUT', '/dashboard', ['version' => 1, 'order' => []]);
        self::assertSame([], $this->api('GET', '')['dashboardOrder']);
    }

    public function testTenantDefaultsUserOverridesResetValidationAndVersionConflict(): void
    {
        $this->getAuthenticatedClient();
        $defaults = $this->api('GET', '/employees');
        self::assertTrue($defaults['canManageTenant']);
        self::assertSame(5, $defaults['effective']['pageSize']);
        $this->api('PUT', '/employees', ['scope' => 'tenant', 'version' => 0, 'pageSize' => 25]);
        self::assertSame(25, $this->api('GET', '/employees')['effective']['pageSize']);
        $this->api('PUT', '/employees', ['scope' => 'user', 'version' => 0, 'pageSize' => 50]);
        $this->api('PUT', '/employees', ['scope' => 'tenant', 'version' => 1, 'pageSize' => 10]);
        self::assertSame(50, $this->api('GET', '')['pageSizes']['employees']);
        $this->api('PUT', '/employees', ['scope' => 'user', 'version' => 1, 'pageSize' => null]);
        self::assertSame(10, $this->api('GET', '/employees')['effective']['pageSize']);
        $this->api('PUT', '/employees', ['scope' => 'tenant', 'version' => 0, 'pageSize' => 100], 409);
        foreach ([['pageSize' => 200], ['pageSize' => null], ['pageSize' => 25, 'userUUID' => Uuid::uuid4()->toString()], ['pageSize' => '25']] as $changes) {
            $this->api('PUT', '/employees', array_replace(['scope' => 'tenant', 'version' => 2, 'pageSize' => 10], $changes), 422);
        }
        $this->api('GET', '/unknown', [], 404);
    }

    public function testChannelsCannotBypassTenantAndSecretsAreNeverReturned(): void
    {
        $this->getAuthenticatedClient();
        $config = ['scope' => 'tenant', 'version' => 0, 'pageSize' => 10, 'channels' => ['internal' => true, 'email' => true, 'sms' => false], 'sms' => ['sender' => 'MapaSmakow', 'limit' => 100, 'quotaMode' => 'monthly', 'apiKey' => 'test-secret-not-a-real-key']];
        $saved = $this->api('PUT', '/notifications', $config);
        self::assertTrue($saved['tenant']['sms']['hasApiKey']);
        self::assertStringNotContainsString('test-secret', $this->client->getResponse()->getContent());
        $cipher = self::getContainer()->get('doctrine')->getConnection()->fetchOne("SELECT payload FROM application_preference WHERE owner = 'tenant' AND resource = 'notifications'");
        self::assertStringNotContainsString('test-secret', $cipher);
        $this->api('PUT', '/notifications', ['scope' => 'user', 'version' => 0, 'pageSize' => null, 'channels' => ['sms' => true, 'email' => false]]);
        $state = $this->api('GET', '/notifications');
        self::assertFalse($state['effective']['channels']['sms']);
        self::assertFalse($state['effective']['channels']['email']);
        $config['version'] = 1;
        $config['channels']['sms'] = true;
        $config['sms']['apiKey'] = '';
        $this->api('PUT', '/notifications', $config);
        self::assertTrue($this->api('GET', '/notifications')['effective']['channels']['sms']);
        $this->api('PUT', '/notifications', ['scope' => 'user', 'version' => 1, 'pageSize' => null, 'sms' => ['apiKey' => 'forbidden']], 422);
    }

    public function testBrevoSendIsQuotaLimitedIdempotentAndCountsUnicodeSegments(): void
    {
        $this->getAuthenticatedClient();
        $manager = self::getContainer()->get('doctrine')->getManager();
        self::assertStringContainsString('_test', $manager->getConnection()->getDatabase());
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com']);
        $preferences = self::getContainer()->get(PreferenceService::class);
        $preferences->save('notifications', 'tenant', ['scope' => 'tenant', 'version' => 0, 'pageSize' => 5, 'channels' => ['sms' => true], 'sms' => ['sender' => 'MapaSmakow', 'limit' => 2, 'quotaMode' => 'monthly', 'apiKey' => 'test-key']]);
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls) {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('https://api.brevo.com/v3/transactionalSMS/send', $url);
            $body = json_decode($options['body'], true);
            self::assertSame('48123456789', $body['recipient']);
            self::assertSame('transactional', $body['type']);

            return new MockResponse('{"messageId":12345}', ['http_code' => 201]);
        });
        $sender = new BrevoSmsSender($manager->getConnection(), $preferences, self::getContainer()->get(PreferenceSecrets::class), $http, new NullLogger());
        $id = Uuid::uuid4()->toString();
        $sender->send($id, $user->getUUID()->toString(), '+48 123-456-789', 'Hello');
        $sender->send($id, $user->getUUID()->toString(), '+48 123-456-789', 'Hello');
        $sender->send(Uuid::uuid4()->toString(), $user->getUUID()->toString(), '+48123456789', 'World');
        $sender->send(Uuid::uuid4()->toString(), $user->getUUID()->toString(), '+48123456789', 'Blocked');
        self::assertSame(2, $calls);
        self::assertSame(2, $preferences->smsUsed('monthly'));
        self::assertSame(['units' => 1, 'unicode' => false], SmsSegments::count(str_repeat('A', 160)));
        self::assertSame(['units' => 2, 'unicode' => false], SmsSegments::count(str_repeat('A', 161)));
        self::assertSame(['units' => 2, 'unicode' => true], SmsSegments::count(str_repeat('ą', 71)));
        self::assertSame(['units' => 2, 'unicode' => false], SmsSegments::count(str_repeat('^', 81)));
    }
}
