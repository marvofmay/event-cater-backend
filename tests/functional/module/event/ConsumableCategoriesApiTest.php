<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\Module\Company\Domain\Entity\User;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsumableCategoriesApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testDefaultCategoriesAreSeededIdempotently(): void
    {
        $this->getAuthenticatedClient();
        $categories = $this->api('GET', '/api/consumable-categories');
        self::assertSame(7, $categories['total']);
        self::assertContains('Napoje i dodatki', array_column($categories['items'], 'name'));
        self::assertContains('Opakowania', array_column($categories['items'], 'name'));
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:initialize-consumable-categories'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame($categories['items'], $this->api('GET', '/api/consumable-categories')['items']);
    }

    public function testCatalogCategoriesRenameFilterAndMoveArchivedProducts(): void
    {
        $this->getAuthenticatedClient();
        $category = $this->api('POST', '/api/consumable-categories', ['name' => 'Dodatki do kawy'], 201);
        $this->api('POST', '/api/consumable-categories', ['name' => ' dodatki do kawy '], 409);
        $details = ['code' => 'MILK', 'name' => 'Mleko 1 l', 'categoryUuid' => $category['uuid'], 'unit' => 'carton'];
        $item = $this->api('POST', '/api/consumables', $details, 201);
        self::assertSame($category['uuid'], $item['categoryUuid']);
        self::assertSame('Dodatki do kawy', $item['category']);
        self::assertSame(1, $this->api('GET', '/api/consumables?categoryUuid='.$category['uuid'])['total']);
        $renamed = $this->api('PUT', '/api/consumable-categories/'.$category['uuid'], ['version' => 1, 'name' => 'Dodatki napojowe']);
        self::assertSame('Dodatki napojowe', $this->api('GET', '/api/consumables/'.$item['uuid'])['category']);
        self::assertSame($category['uuid'], $this->api('GET', '/api/consumables/options?q=Mleko')['items'][0]['categoryUuid']);
        self::assertSame('Dodatki napojowe', $this->api('GET', '/api/preparation-options?q=Mleko')['items'][0]['category']);
        $this->api('PUT', '/api/consumable-categories/'.$category['uuid'], ['version' => 1, 'name' => 'Stara nazwa'], 409);
        $item = $this->api('PUT', '/api/consumables/'.$item['uuid'].'/active', ['version' => $item['version'], 'active' => false]);
        $this->api('DELETE', '/api/consumable-categories/'.$category['uuid'], ['version' => $renamed['version']], 422);
        $target = $this->api('GET', '/api/consumable-categories?q=Opakowania')['items'][0];
        $this->api('DELETE', '/api/consumable-categories/'.$category['uuid'], ['version' => $renamed['version'], 'replacementCategoryUuid' => $category['uuid']], 422);
        $this->api('DELETE', '/api/consumable-categories/'.$category['uuid'], ['version' => $renamed['version'], 'replacementCategoryUuid' => $target['uuid']]);
        $moved = $this->api('GET', '/api/consumables/'.$item['uuid']);
        self::assertSame($target['uuid'], $moved['categoryUuid']);
        self::assertFalse($moved['active']);
        self::assertSame($item['version'] + 1, $moved['version']);
        self::assertSame(0, $this->api('GET', '/api/consumable-categories?q=Dodatki napojowe')['total']);
        $this->api('POST', '/api/consumables', array_replace($details, ['code' => 'BAD', 'categoryUuid' => Uuid::uuid4()->toString()]), 404);
        $this->api('POST', '/api/consumables', array_replace($details, ['code' => 'BAD', 'categoryUuid' => null]), 422);
        $legacy = $this->api('POST', '/api/consumables', ['code' => 'LEGACY', 'name' => 'Produkt', 'category' => 'Dotychczasowa grupa', 'unit' => 'piece'], 201);
        self::assertNotEmpty($legacy['categoryUuid']);
        self::assertSame(1, $this->api('GET', '/api/consumables?category=Dotychczasowa%20grupa')['total']);
        $empty = $this->api('POST', '/api/consumable-categories', ['name' => 'Pusta'], 201);
        $this->api('DELETE', '/api/consumable-categories/'.$empty['uuid'], ['version' => 1]);
    }

    public function testCategoryEndpointsRequireConsumablePermissions(): void
    {
        $this->getAuthenticatedClient();
        $user = User::create('supply-viewer@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('supply-viewer@example.test', 'TestOnly123!');
        $this->api('GET', '/api/consumable-categories', [], 403);
        $this->api('POST', '/api/consumable-categories', ['name' => 'Brak dostępu'], 403);
        $this->api('PUT', '/api/consumable-categories/'.Uuid::uuid4()->toString(), ['version' => 1, 'name' => 'Brak dostępu'], 403);
        $this->api('DELETE', '/api/consumable-categories/'.Uuid::uuid4()->toString(), ['version' => 1], 403);
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->api('GET', '/api/consumable-categories', [], 401);
    }

    public function testMigrationPreservesLegacyCategories(): void
    {
        $db = $this->em->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        $db->executeStatement('DROP TABLE consumable_item');
        $db->executeStatement('DROP TABLE consumable_category');
        $db->executeStatement('CREATE TABLE consumable_item (uuid VARCHAR(36) NOT NULL PRIMARY KEY, category VARCHAR(100) NOT NULL) DEFAULT CHARACTER SET utf8mb4');
        foreach ([' Napoje ', 'Napoje', 'Opakowania', ''] as $name) {
            $db->insert('consumable_item', ['uuid' => Uuid::uuid4()->toString(), 'category' => $name]);
        }
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261024090000.php';
        $migration = new \DoctrineMigrations\Version20261024090000($db, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM consumable_item item JOIN consumable_category category ON category.uuid = item.category_uuid'));
        self::assertSame(3, (int) $db->fetchOne('SELECT COUNT(*) FROM consumable_category'));
        $migration = new \DoctrineMigrations\Version20261024090000($db, new \Psr\Log\NullLogger());
        $migration->down(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        self::assertSame(['Inne', 'Napoje', 'Napoje', 'Opakowania'], $db->fetchFirstColumn('SELECT category FROM consumable_item ORDER BY category'));
    }
}
