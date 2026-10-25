<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\Module\Company\Domain\Entity\User;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class EquipmentLocationsApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function details(string $code = 'HEATER'): array
    {
        return ['code' => $code, 'name' => 'Warnik', 'category' => 'Urządzenia grzewcze', 'unit' => 'piece'];
    }

    public function testRequestedLocationsAreSeededWithoutDuplicates(): void
    {
        $this->getAuthenticatedClient();
        $locations = $this->api('GET', '/api/equipment-locations');
        self::assertSame(9, $locations['total']);
        foreach ([
            'pod schodami po stronie prawej (zmywaka)', 'pod schodami po stronie lewej (toalet)',
            'za barem', 'w biurze', 'w magazynie po stronie stołów bilardowych', 'na zmywaku',
            'w przejściu do kuchni', 'w szafie za drzwiami "western"',
            'w magazynie po prawej stronie za drzwiami "western"',
        ] as $name) {
            self::assertContains($name, array_column($locations['items'], 'name'));
        }
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:initialize-equipment-locations'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame($locations['items'], $this->api('GET', '/api/equipment-locations')['items']);
    }

    public function testAssignRenameFilterPreserveAndClearStorageLocation(): void
    {
        $this->getAuthenticatedClient();
        $location = $this->api('GET', '/api/equipment-locations?q=za%20barem')['items'][0];
        $details = $this->details();
        $item = $this->api('POST', '/api/equipment', $details + ['storageLocationUuid' => $location['uuid']], 201);
        self::assertSame($location['uuid'], $item['storageLocationUuid']);
        self::assertSame('za barem', $item['storageLocation']);
        $other = $this->api('POST', '/api/equipment', $this->details('OTHER'), 201);
        self::assertNull($other['storageLocationUuid']);
        self::assertSame(1, $this->api('GET', '/api/equipment?storageLocationUuid='.$location['uuid'])['total']);
        self::assertSame(1, $this->api('GET', '/api/equipment?q=za%20barem')['total']);
        self::assertSame($location['uuid'], $this->api('GET', '/api/equipment/options?q=HEATER')['items'][0]['storageLocationUuid']);
        $location = $this->api('PUT', '/api/equipment-locations/'.$location['uuid'], ['version' => 1, 'name' => 'za barem, półka 2']);
        self::assertSame('za barem, półka 2', $this->api('GET', '/api/equipment/'.$item['uuid'])['storageLocation']);
        $this->api('PUT', '/api/equipment-locations/'.$location['uuid'], ['version' => 1, 'name' => 'Stara nazwa'], 409);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'], $details + ['version' => $item['version']]);
        self::assertSame($location['uuid'], $item['storageLocationUuid']);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'], $details + ['version' => $item['version'], 'storageLocationUuid' => null]);
        self::assertNull($item['storageLocationUuid']);
        self::assertNull($item['storageLocation']);
        $this->api('POST', '/api/equipment', $this->details('BAD') + ['storageLocationUuid' => Uuid::uuid4()->toString()], 404);
        $this->api('POST', '/api/equipment', $this->details('BAD') + ['storageLocationUuid' => 'not-a-uuid'], 422);
        $this->api('GET', '/api/equipment?storageLocationUuid=invalid', [], 422);
    }

    public function testDeletingLocationPreservesActiveAndArchivedEquipment(): void
    {
        $this->getAuthenticatedClient();
        $location = $this->api('POST', '/api/equipment-locations', ['name' => 'Dodatkowa półka'], 201);
        $items = [];
        foreach (['FIRST', 'SECOND'] as $code) {
            $items[] = $this->api('POST', '/api/equipment', $this->details($code) + ['stockQuantity' => 5, 'storageLocationUuid' => $location['uuid']], 201);
        }
        $items[1] = $this->api('PUT', '/api/equipment/'.$items[1]['uuid'].'/active', ['version' => $items[1]['version'], 'active' => false]);
        $this->api('DELETE', '/api/equipment-locations/'.$location['uuid'], ['version' => 10], 409);
        self::assertSame(2, $this->api('GET', '/api/equipment?active=all&storageLocationUuid='.$location['uuid'])['total']);
        $this->api('DELETE', '/api/equipment-locations/'.$location['uuid'], ['version' => 1]);
        foreach ($items as $item) {
            $updated = $this->api('GET', '/api/equipment/'.$item['uuid']);
            self::assertNull($updated['storageLocationUuid']);
            self::assertNull($updated['storageLocation']);
            self::assertSame($item['version'] + 1, $updated['version']);
            self::assertSame($item['active'], $updated['active']);
            self::assertSame(5, $updated['stockQuantity']);
        }
    }

    public function testNamesAndPermissionsAreValidated(): void
    {
        $this->getAuthenticatedClient();
        foreach (['', str_repeat('a', 201)] as $name) {
            $this->api('POST', '/api/equipment-locations', ['name' => $name], 422);
        }
        $this->api('POST', '/api/equipment-locations', ['name' => ' za barem '], 409);
        $user = User::create('locations-viewer@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('locations-viewer@example.test', 'TestOnly123!');
        $this->api('GET', '/api/equipment-locations', [], 403);
        $this->api('POST', '/api/equipment-locations', ['name' => 'Brak dostępu'], 403);
        $this->api('PUT', '/api/equipment-locations/'.Uuid::uuid4()->toString(), ['version' => 1, 'name' => 'Brak dostępu'], 403);
        $this->api('DELETE', '/api/equipment-locations/'.Uuid::uuid4()->toString(), ['version' => 1], 403);
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->api('GET', '/api/equipment-locations', [], 401);
    }
}
