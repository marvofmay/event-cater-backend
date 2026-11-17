<?php

declare(strict_types=1);

namespace App\tests\functional\module\support;

use App\tests\functional\FunctionalTestBase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SupportTicketApiTest extends FunctionalTestBase
{
    private string $userToken = '';
    private string $operatorToken = '';
    private string $storage = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = dirname(__DIR__, 4).'/storage/support-test';
        $this->installControlPlane();
        $this->getAuthenticatedClient();
        $login = json_decode($this->client->getResponse()->getContent(), true);
        $this->userToken = $login['token'];
        $this->operatorToken = $this->operator();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storage)) {
            foreach (scandir($this->storage) ?: [] as $name) {
                if ($name !== '.' && $name !== '..' && is_file($this->storage.'/'.$name)) {
                    unlink($this->storage.'/'.$name);
                }
            }
            @rmdir($this->storage);
        }
        parent::tearDown();
    }

    public function testAuthorSendsATicketAndTheSaasOwnerReadsAnswersAndClosesIt(): void
    {
        $png = $this->image('zrzut.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $text = $this->image('notatka.png', 'to nie jest obraz');
        $this->client->request('POST', '/api/support/tickets', ['category' => 'system_error', 'subject' => 'Pusty ekran', 'message' => ''], ['attachments' => [$text]]);
        self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());

        $this->client->request('POST', '/api/support/tickets', [
            'category' => 'system_error',
            'subject' => 'Pusty ekran po zapisie',
            'message' => 'Po zapisaniu wydarzenia zostaje biała strona.',
        ], ['attachments' => [$png]]);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $created = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $uuid = $created['uuid'];

        $this->client->request('GET', '/api/support/tickets/'.$uuid);
        self::assertResponseIsSuccessful();
        $mine = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Błąd systemu', $mine['categoryLabel']);
        self::assertSame('Nowe', $mine['statusLabel']);
        self::assertCount(1, $mine['messages'][0]['attachments']);
        $attachment = $mine['messages'][0]['attachments'][0]['uuid'];

        $this->client->request('GET', '/api/support/tickets/'.$uuid.'/attachments/'.$attachment);
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('image/png', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->operatorToken);
        $summary = $this->json('GET', '/api/saas/support/summary');
        self::assertGreaterThanOrEqual(1, $summary['attention']);
        $inbox = $this->json('GET', '/api/saas/support/tickets?category=system_error&q=Pusty&tenant=default');
        self::assertSame($uuid, $inbox['items'][0]['uuid']);
        self::assertSame('Firma testowa', $inbox['items'][0]['tenantName']);
        $otherTenant = $this->json('GET', '/api/saas/support/tickets?tenant=inna-firma');
        self::assertSame([], $otherTenant['items']);

        $answered = $this->json('POST', '/api/saas/support/tickets/'.$uuid.'/replies', ['message' => 'Poprawka jest już na środowisku. Daj znać, czy ekran wrócił.', 'status' => 'waiting']);
        self::assertSame('waiting', $answered['status']);
        self::assertSame('user', $answered['messages'][0]['role']);
        self::assertSame('operator', $answered['messages'][1]['role']);
        self::assertSame('Zespół EventCater', $answered['messages'][1]['author']);

        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->userToken);
        $followed = $this->json('POST', '/api/support/tickets/'.$uuid.'/replies', ['message' => 'Nadal pusty.']);
        self::assertSame('open', $followed['status']);

        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->operatorToken);
        $closed = $this->json('PATCH', '/api/saas/support/tickets/'.$uuid.'/status', ['status' => 'resolved']);
        self::assertSame('resolved', $closed['status']);

        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->userToken);
        $this->client->jsonRequest('GET', '/api/saas/support/tickets');
        self::assertResponseStatusCodeSame(403, $this->client->getResponse()->getContent());

        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE support_ticket SET author_uuid=? WHERE uuid=?', ['00000000-0000-4000-8000-000000000099', $uuid]);
        $this->client->request('GET', '/api/support/tickets/'.$uuid);
        self::assertResponseStatusCodeSame(404, $this->client->getResponse()->getContent());
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->operatorToken);
        $this->client->request('GET', '/api/saas/support/tickets/'.$uuid);
        self::assertResponseIsSuccessful();
    }

    public function testMoreThanFiveImagesAreRejected(): void
    {
        $files = [];
        for ($i = 0; $i < 6; ++$i) {
            $files[] = $this->image('zrzut-'.$i.'.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        }
        $this->client->request('POST', '/api/support/tickets', ['category' => 'other', 'subject' => 'Za dużo plików', 'message' => 'Próba'], ['attachments' => $files]);
        self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM support_ticket'));
    }

    /** @param array<string, mixed> $body */
    private function json(string $method, string $url, array $body = []): array
    {
        $this->client->jsonRequest($method, $url, $body);
        self::assertResponseIsSuccessful($this->client->getResponse()->getContent());

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function operator(): string
    {
        $email = 'operator@example.test';
        $password = 'OperatorHaslo123!';
        static::getContainer()->get('limiter.saas_login')->create(hash('sha256', $email.'|127.0.0.1'))->reset();
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->client->jsonRequest('POST', '/api/saas/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful($this->client->getResponse()->getContent());
        $token = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->userToken);

        return $token;
    }

    private function installControlPlane(): void
    {
        $db = $this->em->getConnection();
        if (!$db->createSchemaManager()->tablesExist(['saas_tenant'])) {
            $db->executeStatement("CREATE TABLE saas_tenant (uuid CHAR(36) PRIMARY KEY, slug VARCHAR(63) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL, contact_email VARCHAR(180) NOT NULL, database_name VARCHAR(64) NOT NULL UNIQUE, status VARCHAR(16) NOT NULL, provisioning VARCHAR(16) NOT NULL, version INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->executeStatement("INSERT INTO saas_tenant VALUES ('00000000-0000-4000-8000-000000000001','default','Firma testowa','',DATABASE(),'active','ready',1,NOW(),NOW())");
        }
        if (!$db->createSchemaManager()->tablesExist(['saas_administrator'])) {
            $db->executeStatement('CREATE TABLE saas_administrator (uuid CHAR(36) PRIMARY KEY, email VARCHAR(180) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->executeStatement('CREATE TABLE saas_session (token_hash CHAR(64) PRIMARY KEY, administrator_uuid CHAR(36) NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->insert('saas_administrator', [
                'uuid' => '00000000-0000-4000-8000-0000000000aa',
                'email' => 'operator@example.test',
                'password' => password_hash('OperatorHaslo123!', PASSWORD_DEFAULT),
                'active' => 1,
            ]);
        }
        if (!$db->createSchemaManager()->tablesExist(['support_ticket'])) {
            require_once dirname(__DIR__, 4).'/migrations/Version20261117120000.php';
            $migration = new \DoctrineMigrations\Version20261117120000($db, new \Psr\Log\NullLogger());
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
        }
    }

    private function image(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'support-api');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, test: true);
    }
}
