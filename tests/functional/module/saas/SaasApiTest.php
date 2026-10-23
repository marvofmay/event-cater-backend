<?php

declare(strict_types=1);
namespace App\tests\functional\module\saas;

use App\tests\functional\FunctionalTestBase;
use DoctrineMigrations\Version20261023130000;
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

final class SaasApiTest extends FunctionalTestBase
{
    private array $databases=[];
    private string $adminToken;
    private string $password;
    protected function setUp():void
    {
        parent::setUp();
        $db=$this->em->getConnection();
        require_once dirname(__DIR__,4).'/migrations/Version20261023130000.php';
        $migration=new Version20261023130000($db,new NullLogger()); $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            if (str_starts_with($query->getStatement(),'ALTER TABLE user')) { continue; }
            $db->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
        }
        $this->password=bin2hex(random_bytes(12));
        $db->insert('saas_administrator',['uuid'=>Uuid::uuid4()->toString(),'email'=>'operator@example.test','password'=>password_hash($this->password,PASSWORD_DEFAULT),'active'=>1]);
        $response=$this->api('POST','/api/saas/login',['email'=>'operator@example.test','password'=>$this->password]); $this->adminToken=$response['token'];
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$this->adminToken);
    }
    protected function tearDown():void
    {
        $db=static::getContainer()->get('doctrine')->getConnection();
        $db->executeStatement('USE service_company_test');
        foreach ($this->databases as $database) { $db->executeStatement('DROP DATABASE '.$db->getDatabasePlatform()->quoteSingleIdentifier($database)); }
        parent::tearDown();
    }
    private function api(string $method,string $url,array $body=[],int $status=200):array
    {
        $this->client->jsonRequest($method,$url,$body);
        self::assertResponseStatusCodeSame($status,$this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(),true,flags:JSON_THROW_ON_ERROR);
    }
    private function tenant(string $slug):array
    {
        $tenant=$this->api('POST','/api/saas/tenants',['name'=>'Firma '.$slug,'slug'=>$slug,'ownerEmail'=>'owner@example.test','ownerPassword'=>$this->password],201);
        $this->databases[]='ec_tenant_'.str_replace('-','',$tenant['uuid']);
        self::assertSame('ready',$tenant['provisioning']); self::assertSame('inactive',$tenant['status']);
        return $tenant;
    }
    private function status(array $tenant,string $status):array
    {
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$this->adminToken);
        return $this->api('PATCH','/api/saas/tenants/'.$tenant['uuid'].'/status',['status'=>$status,'version'=>(int)$tenant['version'],'reason'=>'Test zmiany statusu']);
    }
    private function customerLogin(string $slug):string
    {
        $this->client->setServerParameter('HTTP_X_TENANT',$slug);
        $response=$this->api('POST','/api/login',['email'=>'owner@example.test','password'=>$this->password]);
        return $response['token'];
    }
    public function testTenantIsolationLifecycleAndSeparateAdministratorRealm():void
    {
        $suffix=bin2hex(random_bytes(3)); $a=$this->tenant('test-a-'.$suffix); $b=$this->tenant('test-b-'.$suffix);
        $this->client->setServerParameter('HTTP_X_TENANT',$a['slug']);
        $this->api('POST','/api/login',['email'=>'owner@example.test','password'=>$this->password],403);
        $a=$this->status($a,'active'); $b=$this->status($b,'active');
        $application=new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $migrationTester=new \Symfony\Component\Console\Tester\CommandTester($application->find('app:saas:migrate-tenants'));
        self::assertSame(0,$migrationTester->execute(['--tenant'=>$a['slug']]));
        self::assertStringContainsString('0 pending migrations',$migrationTester->getDisplay());

        $tokenA=$this->customerLogin($a['slug']);
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$tokenA);
        $event=$this->api('POST','/api/events',['title'=>'Poufne wydarzenie A','date'=>'2026-12-24'],201);
        $uuid=$event['data']['uuid']??$event['uuid'];
        $this->api('GET','/api/saas/tenants',[],403);
        $this->client->setServerParameter('HTTP_X_TENANT',$b['slug']);
        $this->api('GET','/api/events/'.$uuid,[],401);
        $tokenB=$this->customerLogin($b['slug']); $this->client->setServerParameter('HTTP_Authorization','Bearer '.$tokenB);
        $this->api('GET','/api/events/'.$uuid,[],404);
        $this->api('POST','/api/events',['title'=>'Wydarzenie B','date'=>'2026-12-25'],201);
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$this->adminToken);
        $this->api('GET','/api/events',[],401);
        $a=$this->status($a,'suspended');
        $this->client->setServerParameter('HTTP_X_TENANT',$a['slug']); $this->client->setServerParameter('HTTP_Authorization','Bearer '.$tokenA);
        $this->api('GET','/api/events/'.$uuid,[],403);
        $a=$this->status($a,'active');
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$tokenA); $this->api('GET','/api/events/'.$uuid,[],401);
        $fresh=$this->customerLogin($a['slug']); $this->client->setServerParameter('HTTP_Authorization','Bearer '.$fresh); $this->api('GET','/api/events/'.$uuid);
        $a=$this->status($a,'deleted');
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$this->adminToken);
        $this->api('PATCH','/api/saas/tenants/'.$a['uuid'].'/status',['status'=>'active','version'=>(int)$a['version'],'reason'=>'Invalid shortcut'],409);
        $a=$this->status($a,'inactive'); $a=$this->status($a,'active');
        $fresh=$this->customerLogin($a['slug']); $this->client->setServerParameter('HTTP_Authorization','Bearer '.$fresh); $this->api('GET','/api/events/'.$uuid);
        // Shared development administrator must never be seeded into client databases.
        $this->api('POST','/api/login',['email'=>'admin.ec@gmail.com','password'=>'Admin123!'],401);
        $em=static::getContainer()->get('doctrine')->getManager();
        $unlinked=static::getContainer()->get(\App\Module\Company\Domain\Service\User\UserFactory::class)->create('admin.ec@gmail.com',$this->password);
        $em->persist($unlinked); $em->flush();
        $login=$this->api('POST','/api/login',['email'=>'admin.ec@gmail.com','password'=>$this->password]);
        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$login['token']);
        $this->api('GET','/api/events',[],403);

        $this->client->setServerParameter('HTTP_Authorization','Bearer '.$this->adminToken);
        $detail=$this->api('GET','/api/saas/tenants/'.$a['uuid']);
        self::assertGreaterThanOrEqual(8,count($detail['history']));
        self::assertArrayNotHasKey('database_name',$detail);
        $this->api('PATCH','/api/saas/tenants/'.$a['uuid'].'/status',['status'=>'suspended','version'=>1,'reason'=>'Stale browser'],409);
    }
    public function testQueuedMessagesRequireAnActiveCurrentTenantAndCarryItsIdentity():void
    {
        $middleware=static::getContainer()->get(\App\Module\Saas\Infrastructure\TenantMiddleware::class);
        $tail=$this->createMock(\Symfony\Component\Messenger\Middleware\MiddlewareInterface::class);
        $tail->method('handle')->willReturnCallback(static fn($envelope)=>$envelope);
        $stack=$this->createMock(\Symfony\Component\Messenger\Middleware\StackInterface::class);
        $stack->method('next')->willReturn($tail);
        $envelope=$middleware->handle(new \Symfony\Component\Messenger\Envelope(new \stdClass()),$stack);
        $stamp=$envelope->last(\App\Module\Saas\Infrastructure\TenantStamp::class);
        self::assertSame('default',$stamp->slug); self::assertSame(1,$stamp->version);
        $received=new \Symfony\Component\Messenger\Stamp\ReceivedStamp('test');
        $handled=$middleware->handle($envelope->with($received),$stack);
        self::assertSame($stamp,$handled->last(\App\Module\Saas\Infrastructure\TenantStamp::class));
        foreach ([null,new \App\Module\Saas\Infrastructure\TenantStamp('default',999),new \App\Module\Saas\Infrastructure\TenantStamp('unknown-tenant',1)] as $invalid) {
            $bad=new \Symfony\Component\Messenger\Envelope(new \stdClass(),[$received,...($invalid?[$invalid]:[])]);
            try { $middleware->handle($bad,$stack); self::fail('Unsafe queued message was handled.'); }
            catch (\Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException) { self::assertTrue(true); }
        }
    }
    public function testValidationAndNoAnonymousAdministration():void
    {
        $this->api('POST','/api/saas/tenants',['name'=>'Bad','slug'=>'../../mysql','ownerEmail'=>'x','ownerPassword'=>'short'],400);
        $this->client->setServerParameter('HTTP_Authorization','');
        $this->api('GET','/api/saas/tenants',[],403);
        $this->api('POST','/api/saas/login',['email'=>'operator@example.test','password'=>'invalid'],403);
    }
}
