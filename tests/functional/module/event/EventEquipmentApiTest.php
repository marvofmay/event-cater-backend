<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\ContractType;
use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\Industry;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Entity\User;
use App\Module\Event\Application\Service\StaffPlanner;
use App\Module\Event\Infrastructure\Integration\CompanyEmployeeDirectory;
use App\tests\functional\FunctionalTestBase;
use Doctrine\DBAL\DriverManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EventEquipmentApiTest extends FunctionalTestBase
{
    private array $mediaFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->mediaFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function api(string $method, string $url, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $url, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function event(array $details = []): array
    {
        return $this->api('POST', '/api/events', array_replace(['title' => 'Bankiet', 'date' => '2026-12-31'], $details), 201);
    }

    private function equipment(string $code = 'TABLE-1', string $name = 'Stół cateringowy'): array
    {
        return $this->api('POST', '/api/equipment', ['code' => $code, 'name' => $name, 'category' => 'meble', 'unit' => 'piece'], 201);
    }

    private function employee(bool $active = true): string
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $company = new Company();
        $company->setUUID(Uuid::uuid4()->toString());
        $company->setFullName('Catering');
        $company->setShortName('Catering');
        $company->setNIP('1234567890');
        $company->setREGON('123456789');
        $company->setInternalCode('TEST');
        $company->setActive(true);
        $company->setIndustry($em->getRepository(Industry::class)->findOneBy([]));
        $department = new Department();
        $department->setUUID(Uuid::uuid4()->toString());
        $department->setName('Obsługa');
        $department->setInternalCode('EVENT');
        $department->setActive(true);
        $department->setCompany($company);
        $position = Position::create('Obsługa', null, true);
        $employee = new Employee();
        $employee->setUUID(Uuid::uuid4()->toString());
        $employee->setFirstName('Anna');
        $employee->setLastName('Kowalska');
        $employee->setPESEL('00000000000');
        $employee->setInternalCode('E-1');
        $employee->setEmploymentFrom(new \DateTime('2020-01-01'));
        $employee->setActive($active);
        $employee->setCompany($company);
        $employee->setDepartment($department);
        $employee->setPosition($position);
        $employee->setRole($em->getRepository(Role::class)->findOneBy([]));
        $employee->setContractType($em->getRepository(ContractType::class)->findOneBy([]));
        foreach ([$company, $department, $position, $employee] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        return $employee->getUUID()->toString();
    }

    public function testDraftCrudCalendarHistoryAndStaleVersion(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        self::assertSame(1, $event['version']);
        self::assertSame('draft', $event['status']);
        $updated = $this->api('PUT', '/api/events/'.$event['uuid'], ['version' => 1, 'title' => 'Bankiet firmowy', 'date' => '2027-01-01']);
        self::assertSame(2, $updated['version']);
        $this->api('PUT', '/api/events/'.$event['uuid'], ['version' => 1, 'title' => 'Nadpisane', 'date' => '2026-12-31'], 409);
        $list = $this->api('GET', '/api/events?from=2027-01-01&to=2027-01-01&q=firmowy');
        self::assertSame(1, $list['total']);
        $history = $this->api('GET', '/api/events/'.$event['uuid'].'/history');
        self::assertSame(2, $history['total']);
        self::assertSame([2, 1], array_column($history['items'], 'revision'));
        $this->api('GET', '/api/events?limit=1000', [], 422);
        $this->api('GET', '/api/events/'.Uuid::uuid4()->toString(), [], 404);
    }

    public function testInvalidPayloadsHaveCorrectStatusesAndDoNotCreateEvents(): void
    {
        $this->getAuthenticatedClient();
        foreach ([['date' => '2026-12-31'], ['title' => ' ', 'date' => '2026-12-31'], ['title' => 'A', 'date' => '2026-02-30'], ['title' => 'A', 'date' => '2026-12-31', 'guestCount' => -1], ['title' => 'A', 'date' => '2026-12-31', 'startsAt' => '2026-12-31T12:00:00']] as $body) {
            $this->api('POST', '/api/events', $body, 422);
        }
        $this->client->request('POST', '/api/events', [], [], ['CONTENT_TYPE' => 'application/json'], '{broken');
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->api('GET', '/api/events')['total']);
    }

    public function testConfirmationRequiresCompletenessAndTerminalStateIsReadOnly(): void
    {
        $this->getAuthenticatedClient();
        $employee = $this->employee();
        $event = $this->event(['coordinatorUuid' => $employee, 'guestCount' => 120,
            'location' => ['street' => 'Długa 1', 'city' => 'Kraków', 'postalCode' => '30-001'],
            'readyAt' => '2026-12-31T17:00:00+01:00', 'startsAt' => '2026-12-31T18:00:00+01:00', 'endsAt' => '2027-01-01T03:00:00+01:00']);
        $url = '/api/events/'.$event['uuid'];
        $event = $this->api('POST', $url.'/status', ['version' => $event['version'], 'status' => 'planned']);
        $this->api('POST', $url.'/status', ['version' => $event['version'], 'status' => 'confirmed'], 422);
        $event = $this->api('PUT', $url.'/contacts', ['version' => $event['version'], 'contacts' => [
            ['name' => 'Anna', 'phone' => '+48 123 456 789'], ['name' => 'Jan', 'phone' => '+48 987 654 321', 'function' => 'Organizator']]]);
        $event = $this->api('POST', $url.'/status', ['version' => $event['version'], 'status' => 'confirmed']);
        self::assertSame('confirmed', $event['status']);
        $this->api('PUT', $url.'/contacts', ['version' => $event['version'], 'contacts' => []], 422);
        foreach (['in_progress', 'completed'] as $status) {
            $event = $this->api('POST', $url.'/status', ['version' => $event['version'], 'status' => $status]);
        }
        $this->api('PUT', $url.'/menu', ['version' => $event['version'], 'items' => []], 422);
        $this->api('POST', $url.'/status', ['version' => $event['version'], 'status' => 'draft'], 422);
    }

    public function testMenuContactsAndEquipmentSnapshots(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $item = $this->equipment();
        $url = '/api/events/'.$event['uuid'];
        $event = $this->api('PUT', $url.'/menu', ['version' => $event['version'], 'items' => [['name' => 'Krem pomidorowy', 'portions' => 120, 'portionSize' => '250 ml', 'diets' => ['vegetarian'], 'allergens' => ['milk']]]]);
        self::assertSame(['milk'], $event['menu'][0]['allergens']);
        $event = $this->api('PUT', $url.'/equipment/'.$item['uuid'], ['version' => $event['version'], 'quantity' => 12]);
        self::assertSame(12, $event['equipment'][0]['quantity']);
        $this->api('PUT', '/api/equipment/'.$item['uuid'], ['version' => $item['version'], 'code' => 'TABLE-1', 'name' => 'Nowa nazwa', 'category' => 'meble', 'unit' => 'set'], 422);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'], ['version' => $item['version'], 'code' => 'TABLE-1', 'name' => 'Nowa nazwa', 'category' => 'meble', 'unit' => 'piece']);
        self::assertSame('Stół cateringowy', $this->api('GET', $url)['equipment'][0]['name']);
        $this->api('PUT', $url.'/equipment/'.$item['uuid'], ['version' => $event['version'], 'quantity' => 0], 422);
        $item = $this->api('PUT', '/api/equipment/'.$item['uuid'].'/active', ['version' => $item['version'], 'active' => false]);
        self::assertSame([], $this->api('GET', '/api/equipment/options?q=TABLE')['items']);
        self::assertCount(1, $this->api('GET', $url)['equipment']);
        $this->api('PUT', $url.'/equipment/'.$item['uuid'], ['version' => $event['version'], 'quantity' => 13], 422);
        $event = $this->api('DELETE', $url.'/equipment/'.$item['uuid'], ['version' => $event['version']]);
        self::assertSame([], $event['equipment']);
    }

    public function testEquipmentAutocompleteUniquenessArchiveAndVersions(): void
    {
        $this->getAuthenticatedClient();
        $item = $this->equipment();
        $this->api('POST', '/api/equipment', ['code' => 'table-1', 'name' => 'Duplicate', 'category' => 'meble', 'unit' => 'piece'], 409);
        self::assertCount(1, $this->api('GET', '/api/equipment/options?q=TABLE')['items']);
        self::assertSame([], $this->api('GET', '/api/equipment/options?q=%25')['items']);
        $this->api('PUT', '/api/equipment/'.$item['uuid'].'/active', ['version' => 1, 'active' => false]);
        $this->api('PUT', '/api/equipment/'.$item['uuid'].'/active', ['version' => 1, 'active' => true], 409);
        self::assertSame(0, $this->api('GET', '/api/equipment')['total']);
        self::assertSame(1, $this->api('GET', '/api/equipment?active=all')['total']);
    }

    public function testStaffHasMultipleFunctionsConflictsAndCancellationReleasesTime(): void
    {
        $this->getAuthenticatedClient();
        $employee = $this->employee();
        $a = $this->event();
        $b = $this->event();
        $assignment = ['version' => 1, 'functions' => ['driver', 'logistics'], 'startsAt' => '2026-12-31T16:00:00+01:00', 'endsAt' => '2027-01-01T02:00:00+01:00'];
        $a = $this->api('PUT', '/api/events/'.$a['uuid'].'/staff/'.$employee, $assignment);
        self::assertSame(['driver', 'logistics'], $a['staff'][0]['functions']);
        $this->api('PUT', '/api/events/'.$b['uuid'].'/staff/'.$employee, $assignment, 409);
        $this->api('POST', '/api/events/'.$a['uuid'].'/status', ['version' => $a['version'], 'status' => 'cancelled']);
        $b = $this->api('PUT', '/api/events/'.$b['uuid'].'/staff/'.$employee, $assignment);
        self::assertCount(1, $b['staff']);
        $this->api('PUT', '/api/events/'.$b['uuid'].'/staff/'.$employee, array_replace($assignment, ['version' => $b['version'], 'functions' => ['driver', 'driver']]), 422);
        $b = $this->api('DELETE', '/api/events/'.$b['uuid'].'/staff/'.$employee, ['version' => $b['version']]);
        self::assertSame([], $b['staff']);
        self::assertCount(1, $this->api('GET', '/api/events/staff-options?q=Anna')['items']);
        $inactive = $this->employee(false);
        $this->api('PUT', '/api/events/'.$b['uuid'].'/staff/'.$inactive, array_replace($assignment, ['version' => $b['version']]), 422);
    }

    public function testAvailabilityUsesCurrentReadAfterAnotherTransactionCommits(): void
    {
        $this->getAuthenticatedClient();
        $employee = $this->employee();
        $a = $this->event();
        $b = $this->event();
        $db = self::getContainer()->get('doctrine')->getConnection();
        $other = DriverManager::getConnection($db->getParams());
        $other->beginTransaction();
        try {
            self::assertSame(0, (int) $other->fetchOne('SELECT COUNT(*) FROM event_staff_assignment'));
            $db->beginTransaction();
            (new CompanyEmployeeDirectory($db))->requireActive($employee);
            $db->insert('event_staff_assignment', ['uuid' => Uuid::uuid4()->toString(), 'event_uuid' => $a['uuid'], 'employee_uuid' => $employee, 'employee_name' => 'Anna', 'functions' => '["driver"]', 'starts_at' => '2026-12-31 15:00:00', 'ends_at' => '2026-12-31 20:00:00']);
            $db->commit();
            $directory = new CompanyEmployeeDirectory($other);
            $directory->requireActive($employee);
            $this->expectException(PlanningConflict::class);
            (new StaffPlanner($other, $directory))->assertAvailable($employee, $b['uuid'], new \DateTimeImmutable('2026-12-31T16:00:00Z'), new \DateTimeImmutable('2026-12-31T19:00:00Z'));
        } finally {
            $other->rollBack();
            $other->close();
        }
    }

    public function testPrivateMediaDownloadScopeAndRemoval(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $other = $this->event();
        $path = tempnam(sys_get_temp_dir(), 'event-image-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=', true));
        $file = new UploadedFile($path, 'zdjęcie.png', 'image/png', null, true);
        $url = '/api/events/'.$event['uuid'].'/attachments';
        $this->client->request('POST', $url, ['version' => '1', 'description' => 'Sala'], ['file' => $file]);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $result = json_decode($this->client->getResponse()->getContent(), true);
        $media = $result['attachments'][0];
        self::assertArrayNotHasKey('filePath', $media);
        $db = self::getContainer()->get('doctrine')->getConnection();
        $stored = $db->fetchOne('SELECT file_path FROM file WHERE uuid = ?', [$media['fileUuid']]);
        $this->mediaFiles[] = self::getContainer()->getParameter('kernel.project_dir').'/storage/event-media/'.$stored;
        $this->client->request('GET', $url.'/'.$media['uuid']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
        $this->api('GET', '/api/events/'.$other['uuid'].'/attachments/'.$media['uuid'], [], 404);
        $this->api('DELETE', $url.'/'.$media['uuid'], ['version' => $result['version']]);
        $this->api('GET', $url.'/'.$media['uuid'], [], 404);
    }

    public function testUnsafeUploadIsRejectedAndUnauthenticatedRequestsAreDenied(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $path = tempnam(sys_get_temp_dir(), 'event-unsafe-');
        file_put_contents($path, '<?php echo "unsafe";');
        try {
            $this->client->request('POST', '/api/events/'.$event['uuid'].'/attachments', ['version' => '1'], ['file' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true)]);
            self::assertResponseStatusCodeSame(422);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->client->setServerParameter('HTTP_Authorization', '');
        $this->client->request('GET', '/api/events');
        self::assertResponseStatusCodeSame(401);
        $this->client->request('GET', '/api/equipment');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedUserWithoutModulePermissionsIsDenied(): void
    {
        $user = User::create('viewer@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $this->em->persist($user);
        $this->em->flush();
        $this->getAuthenticatedClient('viewer@example.test', 'TestOnly123!');
        $this->api('GET', '/api/events', [], 403);
        $this->api('GET', '/api/equipment', [], 403);
        $this->api('POST', '/api/events', ['title' => 'Denied', 'date' => '2026-12-31'], 403);
    }

    public function testNonAdminCanUseOnlyGrantedEventCapabilities(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $employeeUuid = $this->employee();
        $em = self::getContainer()->get('doctrine')->getManager();
        $employee = $em->find(Employee::class, $employeeUuid);
        $role = Role::create('Menu planner');
        $em->persist($role);
        $employee->setRole($role);
        $access = $em->getRepository(\App\Module\System\Domain\Entity\Access::class)->findOneBy(['name' => 'events']);
        $em->persist(new \App\Module\System\Domain\Entity\RoleAccess($role, $access));
        foreach (['view', 'plan_event_menu'] as $name) {
            $permission = $em->getRepository(\App\Module\System\Domain\Entity\Permission::class)->findOneBy(['name' => $name]);
            $em->persist(new \App\Module\System\Domain\Entity\RoleAccessPermission($role, $access, $permission));
        }
        $user = User::create('menu@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $user->setEmployee($employee);
        $em->persist($user);
        $em->flush();
        $this->getAuthenticatedClient('menu@example.test', 'TestOnly123!');
        $url = '/api/events/'.$event['uuid'];
        $this->api('GET', $url);
        $this->api('PUT', $url.'/menu', ['version' => 1, 'items' => [['name' => 'Zupa', 'portions' => 10]]]);
        $this->api('PUT', $url.'/contacts', ['version' => 2, 'contacts' => []], 403);
        $this->api('PUT', $url.'/staff/'.$employeeUuid, ['version' => 2, 'functions' => ['driver'], 'startsAt' => '2026-12-31T10:00:00Z', 'endsAt' => '2026-12-31T12:00:00Z'], 403);
        $this->api('GET', '/api/equipment', [], 403);
    }


    public function testAdjacentAssignmentsDoNotOverlap(): void
    {
        $this->getAuthenticatedClient();
        $employee = $this->employee();
        $a = $this->event();
        $b = $this->event();
        $this->api('PUT', '/api/events/'.$a['uuid'].'/staff/'.$employee, ['version' => 1, 'functions' => ['driver'], 'startsAt' => '2026-12-31T10:00:00Z', 'endsAt' => '2026-12-31T12:00:00Z']);
        $b = $this->api('PUT', '/api/events/'.$b['uuid'].'/staff/'.$employee, ['version' => 1, 'functions' => ['waiter'], 'startsAt' => '2026-12-31T12:00:00Z', 'endsAt' => '2026-12-31T14:00:00Z']);
        self::assertCount(1, $b['staff']);
    }

    public function testMediaCanBeAddedAfterCompletionButNotAfterCancellation(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        // Isolate the media lifecycle from the confirmation scenario tested separately.
        $db = self::getContainer()->get('doctrine')->getConnection();
        $db->executeStatement("UPDATE catering_event SET status = 'completed' WHERE uuid = ?", [$event['uuid']]);
        $path = tempnam(sys_get_temp_dir(), 'event-completed-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=', true));
        $this->client->request('POST', '/api/events/'.$event['uuid'].'/attachments', ['version' => '1'], ['file' => new UploadedFile($path, 'photo.png', null, null, true)]);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $result = json_decode($this->client->getResponse()->getContent(), true);
        $stored = self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT file_path FROM file WHERE uuid = ?', [$result['attachments'][0]['fileUuid']]);
        $this->mediaFiles[] = self::getContainer()->getParameter('kernel.project_dir').'/storage/event-media/'.$stored;
        self::assertSame('completed', $result['status']);
        self::assertSame(2, $result['version']);
        $cancelled = $this->event();
        $this->api('POST', '/api/events/'.$cancelled['uuid'].'/status', ['version' => 1, 'status' => 'cancelled']);
        $path = tempnam(sys_get_temp_dir(), 'event-cancelled-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=', true));
        $directory = self::getContainer()->getParameter('kernel.project_dir').'/storage/event-media/';
        $before = glob($directory.'*');
        $this->client->request('POST', '/api/events/'.$cancelled['uuid'].'/attachments', ['version' => '2'], ['file' => new UploadedFile($path, 'photo.png', null, null, true)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, glob($directory.'*'), 'Rejected upload must clean its staged file.');
    }

    public function testMigrationRoundTripPreservesOtherModules(): void
    {
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261023090000.php';
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261023100000.php';
        $preparation = new \DoctrineMigrations\Version20261023100000($db, new \Psr\Log\NullLogger());
        $preparation->down(new \Doctrine\DBAL\Schema\Schema());
        foreach ($preparation->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        $migration = new \DoctrineMigrations\Version20261023090000($db, new \Psr\Log\NullLogger());
        $migration->down(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        self::assertFalse($db->createSchemaManager()->tablesExist(['catering_event']));
        self::assertTrue($db->createSchemaManager()->tablesExist(['employee', 'user', 'role']));
        $migration = new \DoctrineMigrations\Version20261023090000($db, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        self::assertTrue($db->createSchemaManager()->tablesExist(['catering_event', 'equipment_item', 'event_attachment', 'event_history', 'event_staff_assignment', 'event_equipment_requirement']));
        $preparation = new \DoctrineMigrations\Version20261023100000($db, new \Psr\Log\NullLogger());
        $preparation->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($preparation->getSql() as $query) {
            $db->executeStatement($query->getStatement());
        }
        self::assertTrue($db->createSchemaManager()->tablesExist(['event_preparation_list', 'event_preparation_line', 'preparation_template', 'consumable_item']));

    }

}
