<?php

declare(strict_types=1);

namespace App\tests\functional\module\kitchen;

use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\ContractType;
use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\Industry;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Entity\User;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Entity\RoleAccess;
use App\Module\System\Domain\Entity\RoleAccessPermission;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class KitchenApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
    private function event(string $title = 'Wydarzenie A', array $items = []): array
    {
        $event = $this->api('POST', '/api/events', ['title' => $title, 'date' => '2026-12-31', 'guestCount' => 100,
            'readyAt' => '2026-12-31T11:00:00+01:00', 'startsAt' => '2026-12-31T12:00:00+01:00', 'endsAt' => '2026-12-31T16:00:00+01:00'], 201);
        return $this->api('PUT', '/api/events/'.$event['uuid'].'/menu', ['version' => $event['version'], 'items' => $items ?: [
            ['name' => 'Krem pomidorowy', 'portions' => 90, 'portionSize' => '250 ml', 'diets' => ['standard'], 'allergens' => ['mleko']],
            ['name' => 'Krem pomidorowy', 'portions' => 10, 'portionSize' => '250 ml', 'diets' => ['wegańska'], 'notes' => 'Osobny pojemnik'],
        ]]);
    }
    private function order(array $event, ?string $owner = null): array
    {
        return $this->api('POST', '/api/events/'.$event['uuid'].'/kitchen-orders', ['eventVersion' => $event['version'], 'responsibleUuid' => $owner], 201);
    }
    private function action(array $order, string $action, array $body = [], int $status = 200): array
    {
        return $this->api('POST', '/api/kitchen-orders/'.$order['uuid'].'/'.$action, ['version' => $order['version'], ...$body], $status);
    }
    private function progress(array $order, string $line, array $body, int $status = 200): array
    {
        return $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'].'/lines/'.$line.'/progress', ['version' => $order['version'], ...$body], $status);
    }
    private function plan(array $order): array
    {
        return array_intersect_key($order, array_flip(['serviceMode', 'productionDate', 'dueAt', 'responsibleUuid', 'notes', 'lines']));
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



    public function testSeparateEventCardsAndDailyPlanAndPrintableWorkflow(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $a = $this->event();
        $b = $this->event('Wydarzenie B', [['name' => 'Krem pomidorowy', 'portions' => 50]]);
        $order = $this->order($a, $owner);
        $second = $this->order($b, $owner);
        self::assertSame(0, $order['revision']);
        self::assertSame(90, $order['lines'][0]['requiredPortions']);
        self::assertSame(['wegańska'], $order['lines'][1]['diets']);
        $this->api('POST', '/api/events/'.$a['uuid'].'/kitchen-orders', ['eventVersion' => $a['version']], 409);
        $day = $this->api('GET', '/api/kitchen-orders?date=2026-12-31');
        self::assertSame(2, $day['total']);
        self::assertCount(2, $day['items']);
        self::assertNotSame($day['items'][0]['eventUuid'], $day['items'][1]['eventUuid']);
        $this->client->request('GET', '/api/kitchen-orders/'.$order['uuid'].'/pdf');
        self::assertResponseStatusCodeSame(409);
        $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 1], 422);
        $order = $this->action($order, 'release');
        self::assertSame(1, $order['revision']);
        $order = $this->action($order, 'start');
        $this->action($order, 'ready', [], 422);
        foreach ($order['lines'] as $line) {
            $order = $this->progress($order, $line['uuid'], ['preparedPortions' => $line['requiredPortions']]);
        }
        $order = $this->action($order, 'ready');
        $this->action($order, 'hand-over', [], 422);
        foreach ($order['lines'] as $line) {
            $order = $this->progress($order, $line['uuid'], ['handedOverPortions' => $line['requiredPortions']]);
        }
        self::assertSame('ready', $order['status']);
        $order = $this->action($order, 'hand-over');
        self::assertSame('handed_over', $order['status']);
        self::assertNotNull($order['handedOverBy']);
        $this->client->request('GET', '/api/kitchen-orders/'.$order['uuid'].'/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
        self::assertGreaterThan(5000, strlen($this->client->getResponse()->getContent()));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $history = $this->api('GET', '/api/kitchen-orders/'.$order['uuid'].'/history');
        self::assertSame('hand-over', $history['items'][0]['action']);
        self::assertSame($order['version'], $history['items'][0]['orderVersion']);
        $forEvent = $this->api('GET', '/api/events/'.$a['uuid'].'/kitchen-orders');
        self::assertSame($order['uuid'], $forEvent['uuid']);
        self::assertSame('draft', $this->api('GET', '/api/kitchen-orders/'.$second['uuid'])['status']);
    }
    public function testOnSitePartyKeepsServiceModeThroughRevisions(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event('Urodziny w bazie firmy');
        $order = $this->api('POST', '/api/events/'.$event['uuid'].'/kitchen-orders', ['eventVersion' => $event['version'], 'serviceMode' => 'on_site', 'productionDate' => '2026-12-30', 'responsibleUuid' => $this->employee()], 201);
        self::assertSame('on_site', $order['serviceMode']);
        $plan = $this->plan($order);
        $plan['lines'][0]['packing'] = 'Bufet: bemar, uzupełniać na sali';
        $order = $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan]);
        $order = $this->action($order, 'release');
        $plan = $this->plan($order);
        $plan['lines'][0]['requiredPortions'] = 95;
        $order = $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Pięć dodatkowych osób na urodzinach', ...$plan]);
        $order = $this->action($order, 'revisions/accept');
        self::assertSame('on_site', $order['serviceMode']);
        self::assertSame('Bufet: bemar, uzupełniać na sali', $order['lines'][0]['packing']);
        $revision = $this->api('GET', '/api/kitchen-orders/'.$order['uuid'].'/revisions/2');
        $html = (new \App\Module\Kitchen\Infrastructure\PDF\KitchenPdfRenderer())->html($revision);
        self::assertStringContainsString('W bazie firmy — przekazanie do obsługi na miejscu', $html);
        $day = $this->api('GET', '/api/kitchen-orders?date=2026-12-30');
        self::assertSame(1, $day['total']);
        $day = $this->api('GET', '/api/kitchen-orders?date=2026-12-31');
        self::assertSame(0, $day['total']);
    }
    public function testMenuRevisionRequiresAcceptanceAndPreservesLiveProduction(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $line = $order['lines'][0]['uuid'];
        $order = $this->progress($order, $line, ['preparedPortions' => 40]);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/menu', ['version' => $event['version'], 'items' => [
            ['name' => 'Krem pomidorowy', 'portions' => 110, 'portionSize' => '250 ml', 'diets' => ['standard'], 'allergens' => ['mleko']],
            ['name' => 'Krem pomidorowy', 'portions' => 10, 'portionSize' => '250 ml', 'diets' => ['wegańska'], 'notes' => 'Osobny pojemnik'],
        ]]);
        $order = $this->api('GET', '/api/kitchen-orders/'.$order['uuid']);
        self::assertTrue($order['sourceChanged']);
        self::assertSame(90, $order['lines'][0]['requiredPortions']);
        $plan = $this->plan($order);
        $plan['lines'][0]['requiredPortions'] = 110;
        $order = $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Dodatkowe porcje zamówione przez klienta', ...$plan]);
        self::assertSame(20, $order['revisionDiff']['changed'][0]['portionsDelta']);
        self::assertSame(1, $order['revision']);
        $this->action($order, 'ready', [], 409);
        // Work recorded while the coordinator and kitchen discuss the proposal must survive acceptance.
        $order = $this->progress($order, $line, ['preparedPortions' => 60]);
        $order = $this->action($order, 'revisions/accept');
        self::assertSame(2, $order['revision']);
        self::assertFalse($order['sourceChanged']);
        self::assertSame(60, $order['lines'][0]['preparedPortions']);
        self::assertSame(110, $order['lines'][0]['requiredPortions']);
        self::assertNull($order['pendingRevision']);
        $old = $this->api('GET', '/api/kitchen-orders/'.$order['uuid'].'/revisions/1');
        self::assertTrue($old['superseded']);
        self::assertSame(90, $old['snapshot']['plan']['lines'][0]['requiredPortions']);
        $new = $this->api('GET', '/api/kitchen-orders/'.$order['uuid'].'/revisions/2');
        self::assertFalse($new['superseded']);
    }
    public function testPendingRevisionBecomesStaleAndCanBeRejectedWithoutChangingProduction(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $plan = $this->plan($order);
        $plan['lines'][0]['requiredPortions'] = 120;
        $order = $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Prośba klienta o większą liczbę porcji', ...$plan]);
        $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Druga propozycja nie może nadpisać pierwszej', ...$plan], 409);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/menu', ['version' => $event['version'], 'items' => [['name' => 'Inne danie', 'portions' => 100]]]);
        $this->action($order, 'revisions/accept', [], 409);
        $order = $this->action($order, 'revisions/reject', ['reason' => 'Menu zostało ponownie zmienione']);
        self::assertNull($order['pendingRevision']);
        self::assertSame(90, $order['lines'][0]['requiredPortions']);
        self::assertSame(1, $order['revision']);
    }
    public function testCorrectionsStaleWritesAndActualCountsSurviveCancellation(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $oldVersion = $order['version'];
        $line = $order['lines'][0]['uuid'];
        $order = $this->progress($order, $line, ['preparedPortions' => 100, 'handedOverPortions' => 90]);
        self::assertSame(10, $order['lines'][0]['excessPreparedPortions']);
        $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'].'/lines/'.$line.'/progress', ['version' => $oldVersion, 'preparedPortions' => 0, 'handedOverPortions' => 0, 'correctionReason' => 'Korekta błędnego wpisu'], 409);
        $this->progress($order, $line, ['preparedPortions' => 80], 422);
        $this->progress($order, $line, ['handedOverPortions' => 80], 422);
        $order = $this->progress($order, $line, ['handedOverPortions' => 80, 'correctionReason' => 'Korekta omyłkowego przekazania']);
        self::assertSame(80, $order['lines'][0]['handedOverPortions']);
        $order = $this->action($order, 'cancel', ['reason' => 'Klient odwołał realizację wydarzenia']);
        self::assertSame('cancelled', $order['status']);
        self::assertSame(100, $order['lines'][0]['preparedPortions']);
        $this->progress($order, $line, ['preparedPortions' => 101], 422);
        $this->action($order, 'release', [], 422);
    }
    public function testNoImplicitTransferOfProducedFoodToDifferentDish(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $order = $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 40]);
        $plan = $this->plan($order);
        $plan['lines'] = [$plan['lines'][1]];
        $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Usunięcie już rozpoczętej pozycji', ...$plan], 422);
        $plan = $this->plan($order);
        $plan['lines'][0]['name'] = 'Nowa zupa';
        $proposal = $this->action($order, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Zmiana potrawy po rozpoczęciu produkcji', ...$plan]);
        $this->action($proposal, 'revisions/accept', [], 422);
        $proposal = $this->action($proposal, 'revisions/reject', ['reason' => 'Należy zachować poprzednią potrawę osobno']);
        $plan = $this->plan($proposal);
        $plan['lines'][0]['requiredPortions'] = 0;
        $plan['lines'][] = ['name' => 'Nowa zupa', 'requiredPortions' => 90];
        $proposal = $this->action($proposal, 'revisions', ['eventVersion' => $event['version'], 'reason' => 'Nowe danie i zachowanie wykonanej produkcji', ...$plan]);
        $order = $this->action($proposal, 'revisions/accept');
        self::assertCount(3, $order['lines']);
        self::assertSame(40, $order['lines'][0]['preparedPortions']);
        self::assertSame(0, $order['lines'][0]['requiredPortions']);
        self::assertSame(0, $order['lines'][2]['preparedPortions']);
    }
    public function testDraftRefreshAndIncompleteOrInactiveResponsibilityGuards(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->order($event);
        $this->action($order, 'release', [], 422);
        $owner = $this->employee();
        $plan = $this->plan($order);
        $plan['responsibleUuid'] = $owner;
        $plan['dueAt'] = '2026-12-31T12:00:00+01:00';
        $order = $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan]);
        $this->action($order, 'release', [], 422);
        $plan['dueAt'] = '2026-12-31T10:00:00+01:00';
        $order = $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan]);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/menu', ['version' => $event['version'], 'items' => [['name' => 'Nowe menu', 'portions' => 100]]]);
        $this->action($order, 'release', [], 409);
        $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => 1, ...$plan], 409);
        $plan['lines'] = [['name' => 'Nowe menu', 'requiredPortions' => 100, 'responsibleUuid' => $this->employee(false)]];
        $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan], 422);
        $plan['lines'] = [['name' => 'Nowe menu', 'requiredPortions' => 100]];
        $order = $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan]);
        self::assertFalse($order['sourceChanged']);
        $order = $this->action($order, 'release');
        self::assertSame('released', $order['status']);
        $this->api('PUT', '/api/kitchen-orders/'.$order['uuid'], ['version' => $order['version'], 'eventVersion' => $event['version'], ...$plan], 422);
    }
    public function testClosedEventBlocksProductionButAllowsExplicitCancellation(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $this->api('POST', '/api/events/'.$event['uuid'].'/status', ['version' => $event['version'], 'status' => 'cancelled']);
        $order = $this->api('GET', '/api/kitchen-orders/'.$order['uuid']);
        self::assertTrue($order['productionBlocked']);
        $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 5], 422);
        $this->action($order, 'start', [], 422);
        $order = $this->action($order, 'cancel', ['reason' => 'Wydarzenie zostało anulowane przez koordynatora']);
        self::assertSame('cancelled', $order['status']);
        $this->api('POST', '/api/events/'.$event['uuid'].'/kitchen-orders', ['eventVersion' => $event['version'] + 1], 422);
    }
    private function grantUser(string $email, array $permissions): void
    {
        $employeeUuid = $this->employee();
        $em = self::getContainer()->get('doctrine')->getManager();
        $employee = $em->find(Employee::class, $employeeUuid);
        $role = Role::create($email);
        $em->persist($role);
        $employee->setRole($role);
        $access = $em->getRepository(Access::class)->findOneBy(['name' => 'orders']);
        $em->persist(new RoleAccess($role, $access));
        foreach ($permissions as $name) {
            $permission = $em->getRepository(Permission::class)->findOneBy(['name' => $name]);
            $em->persist(new RoleAccessPermission($role, $access, $permission));
        }
        $user = User::create($email);
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $user->setEmployee($employee);
        $em->persist($user);
        $em->flush();
    }
    public function testCookAndHandoverPermissionsAreIndependent(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $this->grantUser('cook@example.test', ['view', 'prepare_kitchen_meals']);
        $this->grantUser('handover@example.test', ['view', 'hand_over_kitchen_meals']);
        $this->getAuthenticatedClient('cook@example.test', 'TestOnly123!');
        $order = $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 10]);
        $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 20, 'handedOverPortions' => 10], 403);
        $this->action($order, 'revisions/accept', [], 403);
        $this->client->request('GET', '/api/kitchen-orders/'.$order['uuid'].'/pdf');
        self::assertResponseStatusCodeSame(403);
        $this->getAuthenticatedClient('handover@example.test', 'TestOnly123!');
        $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => 20], 403);
        $order = $this->progress($order, $order['lines'][0]['uuid'], ['handedOverPortions' => 10]);
        self::assertSame(10, $order['lines'][0]['preparedPortions']);
        $this->action($order, 'release', [], 403);
    }
    public function testKitchenPlannerCanSelectStaffWithoutEventAssignmentPermission(): void
    {
        $this->getAuthenticatedClient();
        $this->grantUser('planner@example.test', ['view', 'plan_kitchen_production']);
        $this->getAuthenticatedClient('planner@example.test', 'TestOnly123!');
        $options = $this->api('GET', '/api/kitchen-orders/staff-options?q=Anna');
        self::assertNotEmpty($options['items']);
        $this->api('GET', '/api/events/staff-options', [], 403);
    }
    public function testValidationAuthenticationAndLineScope(): void
    {
        $this->api('GET', '/api/kitchen-orders?date=2026-12-31', [], 401);
        $this->getAuthenticatedClient();
        $event = $this->event();
        $order = $this->action($this->order($event, $this->employee()), 'release');
        $this->api('GET', '/api/kitchen-orders?date=2026-02-30', [], 422);
        $this->api('GET', '/api/kitchen-orders?date=2026-12-31&status=invalid', [], 422);
        $this->api('GET', '/api/kitchen-orders/'.Uuid::uuid4(), [], 404);
        $this->progress($order, Uuid::uuid4()->toString(), ['preparedPortions' => 1], 404);
        foreach ([1.5, '10', -1, 1000001] as $invalid) {
            $this->progress($order, $order['lines'][0]['uuid'], ['preparedPortions' => $invalid], 422);
        }
        $this->progress($order, $order['lines'][0]['uuid'], [], 422);
        $this->progress($order, $order['lines'][0]['uuid'], ['handedOverPortions' => 1], 422);
        $this->api('GET', '/api/kitchen-orders/'.$order['uuid'].'/revisions/999', [], 404);
    }
    public function testMigrationRoundTripPreservesEventsAndPreparation(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261023110000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261023110000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
        }
        self::assertSame($event['title'], $db->fetchOne('SELECT title FROM catering_event WHERE uuid = ?', [$event['uuid']]));
        self::assertSame(1, (int)$db->fetchOne('SELECT COUNT(*) FROM event_preparation_list WHERE event_uuid = ?', [$event['uuid']]));
        self::assertSame(0, (int)$db->fetchOne('SELECT COUNT(*) FROM kitchen_order'));
        $order = $this->order($event, $this->employee());
        self::assertSame('draft', $order['status']);
    }
}
