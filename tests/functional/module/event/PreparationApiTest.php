<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

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

final class PreparationApiTest extends FunctionalTestBase
{
    private function api(string $method, string $url, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $url, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
    private function event(array $details = []): array
    {
        return $this->api('POST', '/api/events', array_replace(['title' => 'Kompletacja', 'date' => '2026-12-31'], $details), 201);
    }
    private function gear(): array
    {
        return $this->api('POST', '/api/equipment', ['code' => 'BOWL', 'name' => 'Bulionówka', 'category' => 'zastawa', 'unit' => 'piece'], 201);
    }
    private function consumable(string $code = 'MILK', string $unit = 'carton'): array
    {
        return $this->api('POST', '/api/consumables', ['code' => $code, 'name' => $code, 'category' => 'spożywcze', 'unit' => $unit], 201);
    }
    private function list(string $eventUuid): array
    {
        return $this->api('GET', '/api/events/'.$eventUuid.'/preparation');
    }
    private function line(array $list, string $kind, string $uuid): array
    {
        foreach ($list['lines'] as $line) {
            if ($line['kind'] === $kind && $line['itemUuid'] === $uuid) {
                return $line;
            }
        }
        self::fail('Expected preparation line was not found.');
    }
    private function configure(array $list, string $owner): array
    {
        return $this->api('PUT', '/api/events/'.$list['eventUuid'].'/preparation', ['version' => $list['version'], 'responsibleUuid' => $owner]);
    }
    private function progress(array $list, string $lineUuid, array $values, int $status = 200): array
    {
        return $this->api('PUT', '/api/events/'.$list['eventUuid'].'/preparation/lines/'.$lineUuid.'/progress', ['version' => $list['version'], ...$values], $status);
    }
    private function workflow(array $list, string $action, array $body = [], int $status = 200): array
    {
        return $this->api('POST', '/api/events/'.$list['eventUuid'].'/preparation/'.$action, ['version' => $list['version'], ...$body], $status);
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


    public function testMixedListPackingLoadingAndReapproval(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $event = $this->event();
        $gear = $this->gear();
        $milk = $this->consumable();
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => 1, 'quantity' => 100]);
        $list = $this->api('PUT', '/api/events/'.$event['uuid'].'/preparation/consumables/'.$milk['uuid'], ['version' => $event['version'], 'requiredQuantity' => 20]);
        self::assertCount(2, $list['lines']);
        $list = $this->configure($list, $owner);
        $list = $this->workflow($list, 'approve');
        $list = $this->workflow($list, 'start');
        $gearLine = $this->line($list, 'equipment', $gear['uuid']);
        $milkLine = $this->line($list, 'consumable', $milk['uuid']);
        $this->progress($list, $gearLine['uuid'], ['loadedQuantity' => 1], 422);
        $list = $this->progress($list, $gearLine['uuid'], ['preparedQuantity' => 100, 'loadedQuantity' => 80]);
        $this->workflow($list, 'ready', [], 422);
        $list = $this->progress($list, $milkLine['uuid'], ['preparedQuantity' => 20, 'loadedQuantity' => 20]);
        $list = $this->workflow($list, 'ready');
        $this->workflow($list, 'confirm-loaded', [], 422);
        $list = $this->progress($list, $gearLine['uuid'], ['loadedQuantity' => 100]);
        $list = $this->workflow($list, 'confirm-loaded');
        self::assertSame('loaded', $list['status']);
        self::assertFalse($list['summary']['hasShortages']);
        self::assertNotNull($this->line($list, 'equipment', $gear['uuid'])['loadedBy']);
        $this->progress($list, $gearLine['uuid'], ['loadedQuantity' => 90], 422);
        $list = $this->progress($list, $gearLine['uuid'], ['loadedQuantity' => 90, 'correctionReason' => 'Rozładunek dziesięciu sztuk do korekty.']);
        self::assertSame('ready_for_loading', $list['status']);
        self::assertNull($list['dispatchedAt']);
        $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => $list['version'], 'quantity' => 90]);
        $list = $this->list($event['uuid']);
        self::assertSame('draft', $list['status']);
        self::assertNull($list['approvedRevision']);
        self::assertSame('100.000', $this->line($list, 'equipment', $gear['uuid'])['preparedQuantity']);
        self::assertSame('10.000', $this->line($list, 'equipment', $gear['uuid'])['extraPrepared']);
        $list = $this->workflow($list, 'approve');
        $list = $this->workflow($list, 'ready');
        $list = $this->workflow($list, 'confirm-loaded');
        self::assertSame('loaded', $list['status']);
        $history = $this->api('GET', '/api/events/'.$event['uuid'].'/history?limit=100');
        self::assertContains('preparation_load_confirmed', array_column($history['items'], 'action'));
    }

    public function testPhysicalProgressCannotBeLostThroughDeletionOrStaleEquipmentApi(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $event = $this->event();
        $gear = $this->gear();
        $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => 1, 'quantity' => 5]);
        $list = $this->configure($this->list($event['uuid']), $owner);
        $list = $this->workflow($list, 'approve');
        $line = $list['lines'][0];
        $list = $this->progress($list, $line['uuid'], ['preparedQuantity' => 5, 'loadedQuantity' => 5]);
        $this->api('DELETE', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => $list['version']], 422);
        $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => 1, 'quantity' => 6], 409);
        $list = $this->progress($list, $line['uuid'], ['preparedQuantity' => 0, 'loadedQuantity' => 0, 'correctionReason' => 'Pozycja wycofana, odłożona do magazynu.']);
        $this->api('DELETE', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => $list['version']]);
        self::assertSame([], $this->list($event['uuid'])['lines']);
    }

    public function testDecimalQuantitiesPackagesAndCatalogSnapshots(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $milk = $this->consumable();
        $coffee = $this->consumable('COFFEE', 'kg');
        $url = '/api/events/'.$event['uuid'].'/preparation/consumables/';
        foreach ([0, -1, '0.5', 0.5, true, '1000000.001'] as $invalid) {
            $this->api('PUT', $url.$milk['uuid'], ['version' => 1, 'requiredQuantity' => $invalid], 422);
        }
        $list = $this->api('PUT', $url.$milk['uuid'], ['version' => 1, 'requiredQuantity' => '20.000']);
        $list = $this->api('PUT', $url.$coffee['uuid'], ['version' => $list['version'], 'requiredQuantity' => '1.125']);
        self::assertSame('1.125', $this->line($list, 'consumable', $coffee['uuid'])['requiredQuantity']);
        $this->api('PUT', '/api/consumables/'.$coffee['uuid'], ['version' => 1, 'code' => 'COFFEE', 'name' => 'Kawa', 'category' => 'spożywcze', 'unit' => 'l'], 422);
        $coffee = $this->api('PUT', '/api/consumables/'.$coffee['uuid'].'/active', ['version' => 1, 'active' => false]);
        self::assertCount(1, $this->api('GET', '/api/preparation-options?kind=consumable')['items']);
        self::assertCount(2, $this->list($event['uuid'])['lines']);
        $this->api('PUT', $url.$coffee['uuid'], ['version' => $list['version'], 'requiredQuantity' => 2], 422);
        $list = $this->api('DELETE', $url.$coffee['uuid'], ['version' => $list['version']]);
        self::assertCount(1, $list['lines']);
        $this->api('POST', '/api/consumables', ['code' => 'milk', 'name' => 'Duplikat', 'category' => 'spożywcze', 'unit' => 'carton'], 409);
    }

    public function testTemplatesGuestRecalculationAndManualOverride(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $event = $this->event(['guestCount' => 100]);
        $gear = $this->gear();
        $coffee = $this->consumable('COFFEE', 'kg');
        $definition = ['name' => 'Przerwa kawowa', 'lines' => [
            ['kind' => 'equipment', 'itemUuid' => $gear['uuid'], 'rule' => ['mode' => 'per_guest', 'quantity' => 1, 'perGuests' => 1, 'reservePercent' => 10]],
            ['kind' => 'consumable', 'itemUuid' => $coffee['uuid'], 'rule' => ['mode' => 'per_guest', 'quantity' => '0.020', 'perGuests' => 1], 'sourceLocation' => 'Magazyn spożywczy'],
        ]];
        $template = $this->api('POST', '/api/preparation-templates', $definition, 201);
        $list = $this->workflow($this->list($event['uuid']), 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 1]);
        self::assertSame('110.000', $this->line($list, 'equipment', $gear['uuid'])['requiredQuantity']);
        self::assertSame('2.000', $this->line($list, 'consumable', $coffee['uuid'])['requiredQuantity']);
        $list = $this->configure($list, $owner);
        $list = $this->workflow($list, 'approve');
        $list = $this->progress($list, $this->line($list, 'equipment', $gear['uuid'])['uuid'], ['preparedQuantity' => 100]);
        $this->api('PUT', '/api/events/'.$event['uuid'], ['version' => $list['version'], 'title' => 'Kompletacja', 'date' => '2026-12-31', 'guestCount' => 150]);
        $list = $this->list($event['uuid']);
        self::assertSame('draft', $list['status']);
        self::assertSame('165.000', $this->line($list, 'equipment', $gear['uuid'])['requiredQuantity']);
        self::assertSame('100.000', $this->line($list, 'equipment', $gear['uuid'])['preparedQuantity']);
        self::assertSame(165, $this->api('GET', '/api/events/'.$event['uuid'])['equipment'][0]['quantity']);
        $definition['lines'][0]['rule']['quantity'] = 2;
        $template = $this->api('PUT', '/api/preparation-templates/'.$template['uuid'], ['version' => 1, ...$definition]);
        self::assertSame('165.000', $this->line($this->list($event['uuid']), 'equipment', $gear['uuid'])['requiredQuantity']);
        $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 1], 409);
        $list = $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 2]);
        self::assertCount(2, $list['lines']);
        self::assertSame('330.000', $this->line($list, 'equipment', $gear['uuid'])['requiredQuantity']);
        $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => $list['version'], 'quantity' => 200]);
        $list = $this->list($event['uuid']);
        $this->api('PUT', '/api/events/'.$event['uuid'], ['version' => $list['version'], 'title' => 'Kompletacja', 'date' => '2026-12-31', 'guestCount' => 200]);
        $list = $this->list($event['uuid']);
        self::assertSame('200.000', $this->line($list, 'equipment', $gear['uuid'])['requiredQuantity']);
        self::assertNull($this->line($list, 'equipment', $gear['uuid'])['calculationRule']);
        self::assertSame('4.000', $this->line($list, 'consumable', $coffee['uuid'])['requiredQuantity']);
    }

    public function testTemplateIsAtomicAndDoesNotGuessMissingGuestCount(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $gear = $this->gear();
        $template = $this->api('POST', '/api/preparation-templates', ['name' => 'Stoły', 'lines' => [['kind' => 'equipment', 'itemUuid' => $gear['uuid'], 'rule' => ['mode' => 'per_guest', 'quantity' => 1, 'perGuests' => 10]]]], 201);
        $list = $this->list($event['uuid']);
        $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 1], 422);
        self::assertSame([], $this->list($event['uuid'])['lines']);
        $this->api('PUT', '/api/preparation-templates/'.$template['uuid'].'/active', ['version' => 1, 'active' => false]);
        $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 2], 422);
    }

    public function testListLineScopeMetadataAndPlanInvalidation(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $a = $this->event();
        $b = $this->event();
        $milk = $this->consumable();
        $list = $this->api('PUT', '/api/events/'.$a['uuid'].'/preparation/consumables/'.$milk['uuid'], ['version' => 1, 'requiredQuantity' => 20]);
        $this->progress($list, $list['lines'][0]['uuid'], ['preparedQuantity' => 1], 422);
        $this->workflow($list, 'approve', [], 422);
        $list = $this->configure($list, $owner);
        $list = $this->api('PUT', '/api/events/'.$a['uuid'].'/preparation/lines/'.$list['lines'][0]['uuid'], ['version' => $list['version'], 'responsibleUuid' => $owner, 'sourceLocation' => 'Chłodnia A', 'instructions' => 'Mleko 1 l, kartony zamknięte.']);
        self::assertSame('Chłodnia A', $list['lines'][0]['sourceLocation']);
        $this->progress($this->list($b['uuid']), $list['lines'][0]['uuid'], ['preparedQuantity' => 1], 404);
        $list = $this->workflow($list, 'approve');
        $this->api('PUT', '/api/events/'.$a['uuid'].'/menu', ['version' => $list['version'], 'items' => [['name' => 'Zupa', 'portions' => 100]]]);
        self::assertSame('draft', $this->list($a['uuid'])['status']);
    }

    public function testMigrationBackfillsExistingEquipmentWithoutCopyingDemand(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $gear = $this->gear();
        $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => 1, 'quantity' => 5, 'notes' => 'Do kuchni']);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase());
        require_once self::getContainer()->getParameter('kernel.project_dir').'/migrations/Version20261023100000.php';
        foreach (['down', 'up'] as $direction) {
            $migration = new \DoctrineMigrations\Version20261023100000($db, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
        }
        $list = $this->list($event['uuid']);
        self::assertCount(1, $list['lines']);
        self::assertSame('5.000', $list['lines'][0]['requiredQuantity']);
        self::assertSame('0.000', $list['lines'][0]['preparedQuantity']);
        self::assertSame('Do kuchni', $list['lines'][0]['planningNotes']);
        self::assertNull($db->fetchOne('SELECT required_quantity FROM event_preparation_line WHERE equipment_requirement_uuid IS NOT NULL'));
    }

    public function testPreparationPlannerCanSelectResponsibleWithoutAssigningEventStaff(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $this->grantUser('preparation-planner@example.test', ['view', 'plan_event_preparation']);
        $this->getAuthenticatedClient('preparation-planner@example.test', 'TestOnly123!');
        $options = $this->api('GET', '/api/events/'.$event['uuid'].'/preparation/staff-options?q=Anna');
        self::assertNotEmpty($options['items']);
        $this->api('GET', '/api/events/staff-options', [], 403);
    }
    private function grantUser(string $email, array $permissions): void
    {
        $employeeUuid = $this->employee();
        $em = self::getContainer()->get('doctrine')->getManager();
        $employee = $em->find(Employee::class, $employeeUuid);
        $role = Role::create($email);
        $em->persist($role);
        $employee->setRole($role);
        $access = $em->getRepository(Access::class)->findOneBy(['name' => 'events']);
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

    public function testPreparationLoadingAndShortageAcceptanceHaveSeparatePermissions(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $event = $this->event();
        $milk = $this->consumable();
        $list = $this->api('PUT', '/api/events/'.$event['uuid'].'/preparation/consumables/'.$milk['uuid'], ['version' => 1, 'requiredQuantity' => 20]);
        $list = $this->configure($list, $owner);
        $list = $this->workflow($list, 'approve');
        $lineUuid = $list['lines'][0]['uuid'];
        $this->grantUser('packer@example.test', ['view', 'prepare_event_catering']);
        $this->grantUser('loader@example.test', ['view', 'load_event_catering', 'dispatch_event_catering']);
        $this->getAuthenticatedClient('packer@example.test', 'TestOnly123!');
        $list = $this->progress($list, $lineUuid, ['preparedQuantity' => 20]);
        $this->progress($list, $lineUuid, ['loadedQuantity' => 10], 403);
        $this->workflow($list, 'confirm-loaded', [], 403);
        $this->getAuthenticatedClient('loader@example.test', 'TestOnly123!');
        $list = $this->progress($list, $lineUuid, ['loadedQuantity' => 10]);
        $this->workflow($list, 'confirm-loaded', [], 422);
        $this->workflow($list, 'confirm-loaded', ['acceptShortagesReason' => 'Kierownik akceptuje brak dziesięciu kartonów.'], 403);
        $this->getAuthenticatedClient();
        $list = $this->workflow($list, 'confirm-loaded', ['acceptShortagesReason' => 'Kierownik akceptuje brak dziesięciu kartonów.']);
        self::assertSame('loaded', $list['status']);
        self::assertTrue($list['summary']['hasShortages']);
        self::assertSame('10.000', $list['acceptedShortages'][0]['missingToLoad']);
        self::assertNotSame($list['lines'][0]['preparedBy'], $list['lines'][0]['loadedBy']);
        $list = $this->progress($list, $lineUuid, ['loadedQuantity' => 5, 'correctionReason' => 'Korekta potwierdzonej ilości na samochodzie.']);
        self::assertSame('ready_for_loading', $list['status']);
        self::assertNull($list['acceptedShortagesReason']);
    }
    public function testPreparationMustBeDispatchedBeforeEventStarts(): void
    {
        $this->getAuthenticatedClient();
        $owner = $this->employee();
        $event = $this->event(['guestCount' => 50, 'coordinatorUuid' => $owner, 'location' => ['street' => 'Długa 1', 'city' => 'Kraków', 'postalCode' => '30-001'], 'readyAt' => '2026-12-31T11:00:00+01:00', 'startsAt' => '2026-12-31T12:00:00+01:00', 'endsAt' => '2026-12-31T15:00:00+01:00']);
        $gear = $this->gear();
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/equipment/'.$gear['uuid'], ['version' => $event['version'], 'quantity' => 5]);
        $event = $this->api('PUT', '/api/events/'.$event['uuid'].'/contacts', ['version' => $event['version'], 'contacts' => [['name' => 'Organizator', 'phone' => '+48 123 456 789']]]);
        foreach (['planned', 'confirmed'] as $status) {
            $event = $this->api('POST', '/api/events/'.$event['uuid'].'/status', ['version' => $event['version'], 'status' => $status]);
        }
        $this->api('POST', '/api/events/'.$event['uuid'].'/status', ['version' => $event['version'], 'status' => 'in_progress'], 422);
        $list = $this->list($event['uuid']);
        $this->api('PUT', '/api/events/'.$event['uuid'].'/preparation', ['version' => $list['version'], 'responsibleUuid' => $owner, 'deadlineAt' => '2026-12-31T12:00:00+01:00'], 422);
        $list = $this->configure($list, $owner);
        $list = $this->workflow($list, 'approve');
        $list = $this->progress($list, $list['lines'][0]['uuid'], ['preparedQuantity' => 5, 'loadedQuantity' => 5]);
        $list = $this->workflow($list, 'ready');
        $list = $this->workflow($list, 'confirm-loaded');
        $event = $this->api('POST', '/api/events/'.$event['uuid'].'/status', ['version' => $list['version'], 'status' => 'in_progress']);
        self::assertSame('in_progress', $event['status']);
    }

    public function testTemplateCannotSilentlyReinterpretChangedCatalogUnit(): void
    {
        $this->getAuthenticatedClient();
        $event = $this->event();
        $supply = $this->consumable('INGREDIENT', 'kg');
        $definition = ['name' => 'Zestaw', 'lines' => [['kind' => 'consumable', 'itemUuid' => $supply['uuid'], 'rule' => ['mode' => 'fixed', 'quantity' => '1.5']]]];
        $template = $this->api('POST', '/api/preparation-templates', $definition, 201);
        $this->api('PUT', '/api/consumables/'.$supply['uuid'], ['version' => 1, 'code' => 'INGREDIENT', 'name' => 'Produkt', 'category' => 'spożywcze', 'unit' => 'l']);
        $list = $this->list($event['uuid']);
        $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => 1], 422);
        self::assertSame([], $this->list($event['uuid'])['lines']);
        $template = $this->api('PUT', '/api/preparation-templates/'.$template['uuid'], ['version' => 1, ...$definition]);
        $list = $this->workflow($list, 'apply-template', ['templateUuid' => $template['uuid'], 'templateVersion' => $template['version']]);
        self::assertSame('l', $list['lines'][0]['unit']);
        self::assertSame('1.500', $list['lines'][0]['requiredQuantity']);
    }

    public function testPreparationAndNewCatalogEndpointsRequireAuthentication(): void
    {
        foreach (['/api/consumables', '/api/preparation-templates', '/api/preparation-options', '/api/events/'.Uuid::uuid4()->toString().'/preparation'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(401);
        }
    }

}
