<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class EventCustomersApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testOnlyNameIsRequiredAndSearchFindsContactAndRegistrationDetails(): void
    {
        $this->api('GET', '/api/event-customers', [], 401);
        $this->api('POST', '/api/event-customers', ['name' => 'Klient'], 401);
        $this->getAuthenticatedClient();
        $minimal = $this->api('POST', '/api/event-customers', ['name' => '  Organizator prywatny  '], 201);
        self::assertSame('Organizator prywatny', $minimal['name']);
        self::assertSame('Polska', $minimal['country']);
        self::assertNull($minimal['city']);
        self::assertNull($minimal['street']);
        self::assertNull($minimal['postalCode']);
        foreach (['nip', 'regon', 'krs', 'address', 'phone', 'email'] as $field) {
            self::assertNull($minimal[$field]);
        }
        $full = $this->api('POST', '/api/event-customers', ['name' => 'Firma testowa', 'nip' => '123-456-78-90', 'regon' => '123456789', 'krs' => '0000123456', 'address' => 'Długa 1, 00-001 Warszawa', 'phone' => '+48123456789', 'email' => 'biuro@example.test'], 201);
        self::assertSame('1234567890', $full['nip']);
        foreach (['Firma', '1234567890', '123456789', '0000123456', '+48123', 'biuro@example.test'] as $search) {
            self::assertSame($full['uuid'], $this->api('GET', '/api/event-customers?q='.urlencode($search))['items'][0]['uuid']);
        }
        self::assertSame([], $this->api('GET', '/api/event-customers?q=%25')['items']);
        foreach ([['name' => ' '], ['name' => str_repeat('x', 201)], ['nip' => '123'], ['regon' => 'abc'], ['krs' => '123'], ['email' => 'broken'], ['phone' => 'abc'], ['address' => str_repeat('x', 501)]] as $invalid) {
            $this->api('POST', '/api/event-customers', array_replace(['name' => 'Test'], $invalid), 422);
        }
        self::assertCount(2, $this->api('GET', '/api/event-customers')['items']);
    }

    public function testLibraryPaginationUsesStableOrderingAndValidatesLimits(): void
    {
        $this->getAuthenticatedClient();
        foreach (['Catering C', 'Catering A', 'Catering B'] as $name) $this->api('POST', '/api/event-customers', ['name' => $name], 201);
        $first = $this->api('GET', '/api/event-customers?limit=2&page=1');
        self::assertSame(3, $first['total']);
        self::assertSame(['Catering A', 'Catering B'], array_column($first['items'], 'name'));
        $second = $this->api('GET', '/api/event-customers?limit=2&page=2');
        self::assertSame(['Catering C'], array_column($second['items'], 'name'));
        self::assertSame(1, $this->api('GET', '/api/event-customers?q=Catering%20B')['total']);
        foreach (['limit=101', 'page=0', 'limit=abc'] as $invalid) $this->api('GET', '/api/event-customers?'.$invalid, status: 422);
    }

    public function testEventStoresDirectoryReferenceAndTrustedNameAndCanChangeOrClearIt(): void
    {
        $this->getAuthenticatedClient();
        $first = $this->api('POST', '/api/event-customers', ['name' => 'Pierwszy klient'], 201);
        $second = $this->api('POST', '/api/event-customers', ['name' => 'Drugi klient'], 201);
        $body = ['title' => 'Przyjęcie', 'date' => '2026-12-31', 'customerUuid' => $first['uuid'], 'customerName' => 'Nieprawdziwa nazwa'];
        $event = $this->api('POST', '/api/events', $body, 201);
        self::assertSame($first['uuid'], $event['customerUuid']);
        self::assertSame($first['name'], $event['customerName']);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'], [...$body, 'version' => $event['version'], 'customerUuid' => $second['uuid']]);
        self::assertSame($second['name'], $event['customerName']);
        $this->api('PUT', '/api/events/'.$event['uuid'], [...$body, 'version' => $event['version'], 'customerUuid' => Uuid::uuid4()->toString()], 404);
        self::assertSame($second['uuid'], $this->api('GET', '/api/events/'.$event['uuid'])['customerUuid']);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'], [...$body, 'version' => $event['version'], 'customerUuid' => null, 'customerName' => null]);
        self::assertNull($event['customerUuid']);
        self::assertNull($event['customerName']);
        $legacy = $this->api('POST', '/api/events', [...$body, 'customerUuid' => null, 'customerName' => 'Stary klient'], 201);
        self::assertSame('Stary klient', $legacy['customerName']);
    }

    public function testMigrationSeedsExistingCustomerNamesWithoutDuplicatingThem(): void
    {
        $this->getAuthenticatedClient();
        $first = $this->api('POST', '/api/events', ['title' => 'Pierwsze', 'date' => '2026-12-30', 'customerName' => 'Dotychczasowy klient'], 201);
        $second = $this->api('POST', '/api/events', ['title' => 'Drugie', 'date' => '2026-12-31', 'customerName' => 'Dotychczasowy klient'], 201);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261031120000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261031120000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
        }
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101090000.php';
        $migration = new \DoctrineMigrations\Version20261101090000($db, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        $items = $this->api('GET', '/api/event-customers')['items'];
        self::assertCount(1, $items);
        foreach ([$first, $second] as $event) {
            self::assertSame($items[0]['uuid'], $this->api('GET', '/api/events/'.$event['uuid'])['customerUuid']);
            self::assertSame('Dotychczasowy klient', $this->api('GET', '/api/events/'.$event['uuid'])['customerName']);
        }
    }

    public function testStructuredAddressFieldsAreOptionalAndPersistIndependently(): void
    {
        $this->getAuthenticatedClient();
        $customer = $this->api('POST', '/api/event-customers', ['name' => 'Adres testowy', 'city' => ' Warszawa ', 'street' => 'Długa 1/2', 'postalCode' => '00-001'], 201);
        self::assertSame('Warszawa', $customer['city']);
        self::assertSame('Długa 1/2', $customer['street']);
        self::assertSame('00-001', $customer['postalCode']);
        self::assertSame('Polska', $customer['country']);
        self::assertSame('Długa 1/2, 00-001 Warszawa, Polska', $customer['address']);
        $found = $this->api('GET', '/api/event-customers?q=Adres')['items'][0];
        self::assertSame($customer, $found);
        $foreign = $this->api('POST', '/api/event-customers', ['name' => 'Adres zagraniczny', 'city' => 'Berlin', 'country' => 'Niemcy', 'postalCode' => '10115'], 201);
        self::assertSame('Niemcy', $foreign['country']);
        self::assertNull($foreign['street']);
        $blank = $this->api('POST', '/api/event-customers', ['name' => 'Bez adresu', 'city' => '', 'street' => '', 'postalCode' => '', 'country' => ''], 201);
        self::assertNull($blank['country']);
        self::assertNull($blank['address']);
        foreach (['city' => 201, 'street' => 201, 'postalCode' => 21, 'country' => 101] as $field => $length) {
            $this->api('POST', '/api/event-customers', ['name' => 'Za długie dane', $field => str_repeat('x', $length)], 422);
        }
    }

    public function testAddressMigrationRetainsLegacyTextWithoutGuessingCityOrStreet(): void
    {
        $this->getAuthenticatedClient();
        $customer = $this->api('POST', '/api/event-customers', ['name' => 'Stary adres', 'address' => 'Opis adresu zapisany przed podziałem'], 201);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101090000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261101090000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
        }
        $found = $this->api('GET', '/api/event-customers?q=Stary')['items'][0];
        self::assertSame($customer['address'], $found['address']);
        self::assertNull($found['city']);
        self::assertNull($found['street']);
        self::assertNull($found['postalCode']);
    }
}
