<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\Module\Company\Domain\Entity\User;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class EquipmentCatalogApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testCategoriesRenameFilterAndProtectUsedCategories(): void
    {
        $this->getAuthenticatedClient();
        $category = $this->api('POST', '/api/equipment-categories', ['name' => 'Zastawa'], 201);
        $item = $this->api('POST', '/api/equipment', [
            'code' => 'SOUP-1', 'name' => 'Miska do zupy', 'slangName' => 'Bulionówka',
            'categoryUuid' => $category['uuid'], 'unit' => 'piece', 'stockQuantity' => 240,
        ], 201);
        self::assertSame(240, $item['stockQuantity']);
        self::assertSame('Bulionówka', $item['slangName']);
        self::assertSame('Zastawa', $item['category']);
        self::assertSame($category['uuid'], $item['categoryUuid']);
        self::assertSame(1, $this->api('GET', '/api/equipment?categoryUuid='.$category['uuid'])['total']);
        self::assertSame(0, $this->api('GET', '/api/equipment?categoryUuid='.Uuid::uuid4()->toString())['total']);
        self::assertSame(1, $this->api('GET', '/api/equipment?q=Bulionówka')['total']);
        self::assertSame('Bulionówka', $this->api('GET', '/api/equipment/options?q=Bulionówka')['items'][0]['slangName']);
        self::assertSame('Bulionówka', $this->api('GET', '/api/preparation-options?q=Bulionówka')['items'][0]['slangName']);
        self::assertSame([], $this->api('GET', '/api/equipment/options?q=%25')['items']);
        $category = $this->api('PUT', '/api/equipment-categories/'.$category['uuid'], ['version' => 1, 'name' => 'Naczynia']);
        self::assertSame('Naczynia', $this->api('GET', '/api/equipment/'.$item['uuid'])['category']);
        $this->api('PUT', '/api/equipment-categories/'.$category['uuid'], ['version' => 1, 'name' => 'Stara nazwa'], 409);
        $this->api('POST', '/api/equipment-categories', ['name' => ' naczynia '], 409);
        $this->api('DELETE', '/api/equipment-categories/'.$category['uuid'], ['version' => $category['version']], 422);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'].'/active', ['version' => $item['version'], 'active' => false]);
        $this->api('DELETE', '/api/equipment-categories/'.$category['uuid'], ['version' => $category['version']], 422);
        $replacement = $this->api('POST', '/api/equipment-categories', ['name' => 'Inne'], 201);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'], [
            'version' => $item['version'], 'code' => 'SOUP-1', 'name' => 'Miska',
            'categoryUuid' => $replacement['uuid'], 'unit' => 'piece', 'stockQuantity' => 120, 'slangName' => null,
        ]);
        self::assertSame(120, $item['stockQuantity']);
        self::assertNull($item['slangName']);
        $this->api('DELETE', '/api/equipment-categories/'.$category['uuid'], ['version' => $category['version']]);
        self::assertSame(0, $this->api('GET', '/api/equipment-categories?q=Naczynia')['total']);
        self::assertSame(1, $this->api('GET', '/api/equipment-categories')['total']);
    }

    public function testInvalidQuantitiesNamesAndMissingCategoriesAreRejected(): void
    {
        $this->getAuthenticatedClient();
        $category = $this->api('POST', '/api/equipment-categories', ['name' => 'Sprzęt'], 201);
        $valid = ['code' => 'A', 'name' => 'Krzesło', 'categoryUuid' => $category['uuid'], 'unit' => 'piece'];
        foreach ([-1, 1.5, '12', 2147483648] as $quantity) {
            $this->api('POST', '/api/equipment', $valid + ['stockQuantity' => $quantity], 422);
        }
        $this->api('POST', '/api/equipment', $valid + ['slangName' => str_repeat('a', 201)], 422);
        $this->api('POST', '/api/equipment', array_replace($valid, ['categoryUuid' => Uuid::uuid4()->toString()]), 404);
        $this->api('POST', '/api/equipment', array_replace($valid, ['categoryUuid' => null]), 422);
        foreach ([' ', str_repeat('a', 101)] as $name) {
            $this->api('POST', '/api/equipment-categories', ['name' => $name], 422);
        }
        self::assertSame(0, $this->api('GET', '/api/equipment')['total']);
        $item = $this->api('POST', '/api/equipment', $valid + ['stockQuantity' => 0], 201);
        self::assertSame(0, $item['stockQuantity']);
        $this->api('PUT', '/api/equipment/'.$item['uuid'], $valid + ['version' => 1, 'stockQuantity' => 4]);
        $this->api('PUT', '/api/equipment/'.$item['uuid'], $valid + ['version' => 1, 'stockQuantity' => 8], 409);
        self::assertSame(4, $this->api('GET', '/api/equipment/'.$item['uuid'])['stockQuantity']);
    }

    public function testLegacyCategoryNamesRemainSupportedAndQuantityIsPreservedWhenOmitted(): void
    {
        $this->getAuthenticatedClient();
        $details = ['code' => 'A', 'name' => 'Stół', 'category' => 'Meble', 'unit' => 'piece'];
        $item = $this->api('POST', '/api/equipment', $details + ['stockQuantity' => 20], 201);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'], $details + ['version' => $item['version']]);
        self::assertSame(20, $item['stockQuantity']);
        $this->api('POST', '/api/equipment', array_replace($details, ['code' => 'B']), 201);
        self::assertSame(1, $this->api('GET', '/api/equipment-categories')['total']);
        self::assertSame(2, $this->api('GET', '/api/equipment?category=Meble')['total']);
    }

    public function testCategoryEndpointsRequireEquipmentPermissions(): void
    {
        $this->getAuthenticatedClient();
        $category = $this->api('POST', '/api/equipment-categories', ['name' => 'Zastawa'], 201);
        $user = User::create('category-viewer@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('category-viewer@example.test', 'TestOnly123!');
        $this->api('GET', '/api/equipment-categories', [], 403);
        $this->api('POST', '/api/equipment-categories', ['name' => 'Brak dostępu'], 403);
        $this->api('PUT', '/api/equipment-categories/'.$category['uuid'], ['version' => 1, 'name' => 'Brak dostępu'], 403);
        $this->api('DELETE', '/api/equipment-categories/'.$category['uuid'], ['version' => 1], 403);
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->api('GET', '/api/equipment-categories', [], 401);
    }
}
