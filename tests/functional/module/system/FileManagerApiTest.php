<?php

declare(strict_types=1);

namespace App\tests\functional\module\system;

use App\Module\Company\Domain\Entity\User;
use App\Module\FileManager\Application\Port\PrivateFileStorage;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileManagerApiTest extends FunctionalTestBase
{
    private array $temporary = [];
    private array $stored = [];

    protected function tearDown(): void
    {
        foreach ([...$this->temporary, ...$this->stored] as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    private function api(string $method = 'GET', string $path = '', array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, '/api/file-manager'.$path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function folder(string $name, ?string $parent = null): string
    {
        return $this->api('POST', '/folders', ['name' => $name, 'parentUuid' => $parent, 'version' => $this->api()['version']], 201)['uuid'];
    }

    private function upload(string $name, string $content, ?string $parent = null, int $status = 201): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'file-manager-test-');
        $this->temporary[] = $path;
        file_put_contents($path, $content);
        $version = $this->api()['version'];
        $this->client->request('POST', '/api/file-manager/upload', array_filter(['name' => $name, 'parentUuid' => $parent, 'version' => (string) $version], static fn ($value) => $value !== null), ['file' => new UploadedFile($path, $name, test: true)]);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        if ($status !== 201) return null;
        $uuid = json_decode($this->client->getResponse()->getContent(), true)['uuid'];
        $db = self::getContainer()->get('doctrine')->getConnection();
        $blob = $db->fetchOne('SELECT blob_name FROM managed_file WHERE uuid = ?', [$uuid]);
        $this->stored[] = self::getContainer()->get(PrivateFileStorage::class)->path($blob);
        return $uuid;
    }

    public function testPersistentTreeUploadDownloadRenameMoveCopyAndRecursiveDelete(): void
    {
        $this->getAuthenticatedClient();
        self::assertSame([], $this->api()['items']);
        $first = $this->folder('Dokumenty');
        $nested = $this->folder('Umowy', $first);
        $target = $this->folder('Archiwum');
        $file = $this->upload('umowa.txt', 'Treść dokumentu testowego.', $nested);
        self::assertCount(4, $this->api()['items']);
        $this->client->request('GET', '/api/file-manager/'.$file.'/download');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame('Treść dokumentu testowego.', file_get_contents($this->client->getResponse()->getFile()->getPathname()));
        $this->api('PATCH', '/'.$file.'/rename', ['name' => 'nowa-umowa.txt', 'version' => $this->api()['version']]);
        $this->api('POST', '/transfer', ['operation' => 'move', 'uuids' => [$first], 'parentUuid' => $target, 'version' => $this->api()['version']]);
        $this->api('POST', '/transfer', ['operation' => 'copy', 'uuids' => [$first, $file], 'parentUuid' => null, 'version' => $this->api()['version']]);
        $copied = $this->api();
        self::assertCount(7, $copied['items']);
        self::assertSame(strlen('Treść dokumentu testowego.') * 2, $copied['usedBytes']);
        $copy = array_values(array_filter($copied['items'], static fn ($row) => $row['name'] === 'Dokumenty' && $row['parentUuid'] === null))[0];
        self::assertNotSame($first, $copy['uuid']);
        $this->api('POST', '/delete', ['uuids' => [$target, $file], 'version' => $copied['version']]);
        self::assertCount(3, $this->api()['items']);
        $this->api('GET', '/'.$file.'/download', status: 404);
        $remainingFile = array_values(array_filter($this->api()['items'], static fn ($row) => $row['type'] === 'file'))[0];
        $this->client->request('GET', '/api/file-manager/'.$remainingFile['uuid'].'/download');
        self::assertResponseIsSuccessful();
        $this->api('POST', '/delete', ['uuids' => [$copy['uuid']], 'version' => $this->api()['version']]);
        self::assertSame([], $this->api()['items']);
        self::assertFileDoesNotExist($this->stored[0]);
    }

    public function testValidationConcurrencyAndBatchAtomicity(): void
    {
        $this->getAuthenticatedClient();
        $first = $this->folder('Dokumenty');
        $nested = $this->folder('Umowy', $first);
        $version = $this->api()['version'];
        $this->api('POST', '/folders', ['name' => 'Nowy', 'version' => $version - 1], 409);
        foreach (['../sekret', '.hidden', 'a/b', 'a\\b', 'a:b', 'folder.', str_repeat('a', 151)] as $name) {
            $this->api('POST', '/folders', ['name' => $name, 'version' => $version], 422);
        }
        $this->api('POST', '/folders', ['name' => 'dokumenty', 'version' => $version], 409);
        foreach (['move', 'copy'] as $operation) {
            $this->api('POST', '/transfer', ['operation' => $operation, 'uuids' => [$first], 'parentUuid' => $nested, 'version' => $version], 422);
        }
        $this->api('POST', '/delete', ['uuids' => [$first, Uuid::uuid4()->toString()], 'version' => $version], 404);
        $this->api('POST', '/delete', ['uuids' => [], 'version' => $version], 422);
        $this->api('POST', '/delete', ['uuids' => ['00000000-0000-0000-0000-000000000000'], 'version' => $version], 422);
        self::assertSame($version, $this->api()['version']);
        self::assertCount(2, $this->api()['items']);
        $file = $this->upload('plik.txt', 'Bezpieczna treść.');
        $this->api('PATCH', '/'.$file.'/rename', ['name' => 'plik.php', 'version' => $this->api()['version']], 422);
        $this->api('POST', '/folders', ['name' => 'Zły rodzic', 'parentUuid' => $file, 'version' => $this->api()['version']], 422);
    }

    public function testUploadsRejectExecutablesSpoofedMimeInvalidOfficeEmptyAndOversizedFiles(): void
    {
        $this->getAuthenticatedClient();
        $version = $this->api()['version'];
        foreach ([['shell.php', '<?php echo 1;'], ['shell.jpg', '<?php echo 1;'], ['shell.pdf', '<html>HTML</html>'], ['document.docx', 'Not a ZIP'], ['picture.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'], ['empty.txt', ''], ['large.txt', str_repeat('a', 20 * 1024 * 1024 + 1)]] as [$name, $content]) $this->upload($name, $content, status: 422);
        foreach (['docx', 'xlsx'] as $extension) {
            $path = tempnam(sys_get_temp_dir(), 'office-test-');
            $this->temporary[] = $path;
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
            $zip->addFromString($extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml', '<?xml version="1.0"?><document/>');
            $zip->close();
            $this->upload('document.'.$extension, file_get_contents($path));
            $zip->open($path);
            $zip->addFromString('word/vbaProject.bin', 'macro');
            $zip->close();
            $this->upload('macro.'.$extension, file_get_contents($path), status: 422);
        }
        self::assertSame($version + 2, $this->api()['version']);
        self::assertCount(2, $this->api()['items']);
    }

    public function testUsersIncludingAdministratorsHaveSeparatePrivateCatalogs(): void
    {
        $this->getAuthenticatedClient();
        $firstFolder = $this->folder('Dokumenty');
        $file = $this->upload('prywatne.txt', 'Treść dostępna tylko właścicielowi.', $firstFolder);
        $firstSnapshot = $this->api();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $second = User::create('private-files-second@example.test');
        $second->makeTenantAdministrator();
        $second->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager->persist($second);
        $manager->flush();
        $this->getAuthenticatedClient('private-files-second@example.test', 'TestOnly123!');
        self::assertSame([], $this->api()['items']);
        self::assertSame(1, $this->api()['version']);
        self::assertSame(0, $this->api()['usedBytes']);
        $this->api('GET', '/'.$file.'/download', status: 404);
        $this->api('PATCH', '/'.$file.'/rename', ['name' => 'cudze.txt', 'version' => 1], 404);
        $this->api('POST', '/folders', ['name' => 'W cudzym folderze', 'parentUuid' => $firstFolder, 'version' => 1], 404);
        foreach (['move', 'copy'] as $operation) $this->api('POST', '/transfer', ['operation' => $operation, 'uuids' => [$file], 'parentUuid' => null, 'version' => 1], 404);
        $secondFolder = $this->folder('Dokumenty');
        $version = $this->api()['version'];
        $this->api('POST', '/delete', ['uuids' => [$secondFolder, $file], 'version' => $version], 404);
        self::assertCount(1, $this->api()['items']);
        $this->api('POST', '/transfer', ['operation' => 'move', 'uuids' => [$secondFolder], 'parentUuid' => $firstFolder, 'version' => $version], 404);
        $this->getAuthenticatedClient();
        self::assertSame($firstSnapshot, $this->api());
        $this->api('GET', '/'.$secondFolder.'/download', status: 404);
    }

    public function testOwnershipMigrationPreservesFilesAndSeparatesMixedAuthorBranches(): void
    {
        $this->getAuthenticatedClient();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $db = $manager->getConnection();
        $first = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com'])->getUUID()->toString();
        $second = Uuid::uuid4()->toString();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101130000.php';
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101140000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261101130000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement());
        }
        $folder = Uuid::uuid4()->toString();
        $file = Uuid::uuid4()->toString();
        $copy = Uuid::uuid4()->toString();
        $otherFolder = Uuid::uuid4()->toString();
        $blob = Uuid::uuid4()->toString();
        foreach ([[$folder, \App\Module\FileManager\Application\FileManager::ROOT, 'Folder', 'folder', $first], [$otherFolder, \App\Module\FileManager\Application\FileManager::ROOT, 'Inny folder', 'folder', $second], [$file, $folder, 'plan.txt', 'file', $second], [$copy, $otherFolder, 'plan.txt', 'file', $second]] as [$uuid, $parent, $name, $kind, $owner]) {
            $db->insert('managed_file', ['uuid' => $uuid, 'parent_uuid' => $parent, 'name' => $name, 'name_key' => \App\Module\FileManager\Domain\FileName::key($name), 'kind' => $kind, 'created_by' => $owner, 'blob_name' => $kind === 'file' ? $blob : null, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        $migration = new \DoctrineMigrations\Version20261101140000($db, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        self::assertSame($second, $db->fetchOne('SELECT owner_uuid FROM managed_file WHERE uuid = ?', [$file]));
        self::assertSame(\App\Module\FileManager\Infrastructure\CurrentFileOwner::rootFor($second), $db->fetchOne('SELECT parent_uuid FROM managed_file WHERE uuid = ?', [$file]));
        self::assertSame($blob, $db->fetchOne('SELECT blob_name FROM managed_file WHERE uuid = ?', [$copy]));
        self::assertSame([$folder], array_column($this->api()['items'], 'uuid'));
        $this->api('GET', '/'.$file.'/download', status: 404);
    }

    public function testMigrationAddsReadPermissionsWithoutGrantingMutation(): void
    {
        $manager = self::getContainer()->get('doctrine')->getManager();
        $role = \App\Module\Company\Domain\Entity\Role::create('Odczyt dokumentów');
        $access = $manager->getRepository(\App\Module\System\Domain\Entity\Access::class)->findOneBy(['name' => 'file_manager']);
        $manager->persist($role);
        $manager->persist(new \App\Module\System\Domain\Entity\RoleAccess($role, $access));
        $manager->flush();
        $db = $manager->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261101130000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261101130000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement());
        }
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM managed_file'));
        $permissions = $db->fetchFirstColumn('SELECT permission.name FROM role_access_permission rap JOIN permission ON permission.uuid = rap.permission_uuid WHERE rap.role_uuid = ? AND rap.access_uuid = ? AND rap.deleted_at IS NULL ORDER BY permission.name', [$role->getUUID()->toString(), $access->getUUID()->toString()]);
        self::assertSame(['list', 'view'], $permissions);
    }

    public function testAnonymousAndAccountsWithoutAccessCannotReadOrMutateFiles(): void
    {
        $this->api(status: 401);
        $this->getAuthenticatedClient();
        $file = $this->upload('private.txt', 'Private content');
        $manager = self::getContainer()->get('doctrine')->getManager();
        $user = User::create('files-denied@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $manager->persist($user);
        $manager->flush();
        $this->getAuthenticatedClient('files-denied@example.test', 'TestOnly123!');
        $this->api(status: 403);
        $this->api('GET', '/'.$file.'/download', status: 403);
        $this->api('POST', '/folders', ['name' => 'No', 'version' => 1], 403);
        $this->api('POST', '/upload', status: 403);
        $this->api('PATCH', '/'.$file.'/rename', ['name' => 'changed.txt', 'version' => 1], 403);
        $this->api('POST', '/transfer', ['operation' => 'copy'], 403);
        $this->api('POST', '/transfer', ['operation' => 'move'], 403);
        $this->api('POST', '/delete', ['uuids' => [$file], 'version' => 1], 403);
    }
}
