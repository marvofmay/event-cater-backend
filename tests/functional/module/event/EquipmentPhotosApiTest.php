<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\Module\Company\Domain\Entity\User;
use App\Module\Equipment\Infrastructure\Storage\EquipmentPhotoStorage;
use App\tests\functional\FunctionalTestBase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EquipmentPhotosApiTest extends FunctionalTestBase
{
    private array $equipmentUuids = [];
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        $storage = new EquipmentPhotoStorage(dirname(__DIR__, 4).'/storage/equipment-photos');
        foreach ($this->equipmentUuids as $uuid) {
            $storage->discardAll($uuid);
        }
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function equipment(string $code = 'HEATER'): array
    {
        $item = $this->api('POST', '/api/equipment', ['code' => $code, 'name' => 'Warnik na wodę', 'category' => 'Urządzenia grzewcze', 'unit' => 'piece'], 201);
        $this->equipmentUuids[] = $item['uuid'];
        return $item;
    }

    private function file(string $name = 'warnik.png', ?string $content = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'equipment-photo-');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $content ?? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=', true));
        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(array $item, int $status = 201, ?UploadedFile $file = null): array
    {
        $this->client->request('POST', '/api/equipment/'.$item['uuid'].'/photos', ['version' => (string) $item['version']], ['file' => $file ?? $this->file()]);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function files(string $uuid): array
    {
        return glob(dirname(__DIR__, 4).'/storage/equipment-photos/'.$uuid.'/*') ?: [];
    }

    public function testUploadGalleryDownloadRemovalAndEquipmentDeletion(): void
    {
        $this->getAuthenticatedClient();
        $item = $this->equipment();
        self::assertSame([], $item['photos']);
        $item = $this->upload($item);
        self::assertSame(2, $item['version']);
        self::assertCount(1, $item['photos']);
        $photoUuid = $item['photos'][0]['uuid'];
        self::assertArrayNotHasKey('storedName', $item['photos'][0]);
        self::assertSame('image/png', $item['photos'][0]['mimeType']);
        $item = $this->upload($item, file: $this->file('warnik-z-boku.png'));
        self::assertSame(3, $item['version']);
        self::assertCount(2, $item['photos']);
        self::assertCount(2, $this->files($item['uuid']));
        self::assertCount(2, $this->api('GET', '/api/equipment')['items'][0]['photos']);
        $url = '/api/equipment/'.$item['uuid'].'/photos/'.$photoUuid;
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringStartsWith('inline;', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('private', $this->client->getResponse()->headers->get('Cache-Control'));
        $other = $this->equipment('OTHER');
        $this->api('GET', '/api/equipment/'.$other['uuid'].'/photos/'.$photoUuid, [], 404);
        $this->api('DELETE', '/api/equipment/'.$other['uuid'].'/photos/'.$photoUuid, ['version' => 1], 404);
        $this->api('DELETE', $url, ['version' => 1], 409);
        self::assertCount(2, $this->files($item['uuid']));
        $item = $this->api('DELETE', $url, ['version' => $item['version']]);
        self::assertSame(4, $item['version']);
        self::assertCount(1, $item['photos']);
        self::assertCount(1, $this->files($item['uuid']));
        $this->api('GET', $url, [], 404);
        $remainingUuid = $item['photos'][0]['uuid'];
        $this->api('DELETE', '/api/equipment/'.$item['uuid'], ['version' => $item['version']]);
        self::assertSame([], $this->files($item['uuid']));
        self::assertFalse(is_dir(dirname(__DIR__, 4).'/storage/equipment-photos/'.$item['uuid']));
        $this->api('GET', '/api/equipment/'.$item['uuid'].'/photos/'.$remainingUuid, [], 404);
    }

    public function testRejectedUploadsCleanFilesAndValidateImages(): void
    {
        $this->getAuthenticatedClient();
        $item = $this->equipment();
        $updated = $this->upload($item);
        $before = $this->files($item['uuid']);
        $this->upload($item, 409);
        self::assertSame($before, $this->files($item['uuid']));
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"/>', '<?php echo 1;', 'not an image'] as $content) {
            $this->upload($updated, 422, $this->file('image.png', $content));
        }
        self::assertSame($before, $this->files($item['uuid']));
        $this->client->request('POST', '/api/equipment/'.$item['uuid'].'/photos', ['version' => '0'], ['file' => $this->file()]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/api/equipment/'.$item['uuid'].'/photos', ['version' => (string) $updated['version']]);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->api('GET', '/api/equipment/'.$item['uuid'])['photos']);
    }

    public function testGalleryLimitDoesNotLeaveAnExtraFile(): void
    {
        $this->getAuthenticatedClient();
        $item = $this->equipment();
        for ($index = 0; $index < 20; ++$index) {
            $item = $this->upload($item);
        }
        $this->upload($item, 422);
        self::assertCount(20, $this->files($item['uuid']));
        self::assertCount(20, $this->api('GET', '/api/equipment/'.$item['uuid'])['photos']);
    }

    public function testPhotoEndpointsRequireEquipmentPermissions(): void
    {
        $this->getAuthenticatedClient();
        $item = $this->upload($this->equipment());
        $photoUuid = $item['photos'][0]['uuid'];
        $user = User::create('photos-viewer@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager = self::getContainer()->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('photos-viewer@example.test', 'TestOnly123!');
        $this->api('GET', '/api/equipment/'.$item['uuid'].'/photos/'.$photoUuid, [], 403);
        $this->upload($item, 403);
        $this->api('DELETE', '/api/equipment/'.$item['uuid'].'/photos/'.$photoUuid, ['version' => $item['version']], 403);
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->api('GET', '/api/equipment/'.$item['uuid'].'/photos/'.$photoUuid, [], 401);
    }
}
