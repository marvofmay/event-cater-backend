<?php

declare(strict_types=1);

namespace App\tests\functional\module\company\presentation\api\controller\role;

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
use App\Module\System\Domain\Entity\RoleAccessPermission;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class RoleAuthorizationTest extends FunctionalTestBase
{
    private string $adminToken;
    private string $roleUuid;
    private array $accesses;
    private array $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getAuthenticatedClient();
        $this->adminToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
        $manager = static::getContainer()->get('doctrine')->getManager();
        $role = Role::create('Testowa rola');
        $manager->persist($role);
        $manager->flush();
        $this->roleUuid = $role->getUUID()->toString();
        $this->accesses = array_column($this->api('GET', '/api/accesses')['data'], null, 'name');
        $this->permissions = array_column($this->api('GET', '/api/permissions')['data'], 'uuid', 'name');
    }

    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function selection(string $access, array $permissions): array
    {
        return ['accessUUID' => $this->accesses[$access]['uuid'], 'permissionsUUIDs' => array_map(fn (string $name): string => $this->permissions[$name], $permissions)];
    }

    private function save(array $selections, int $status = 200): void
    {
        $this->api('PUT', '/api/roles/'.$this->roleUuid.'/accesses', ['accesses' => $selections], $status);
    }

    private function assigned(): array
    {
        return $this->api('GET', '/api/roles/'.$this->roleUuid)['data']['accesses'];
    }

    public function testCatalogCoversEveryAccessIncludingCatering(): void
    {
        self::assertCount(count(AccessEnum::cases()), $this->accesses);
        foreach (AccessEnum::cases() as $access) {
            [, $name] = explode('.', $access->value);
            $expected = array_map(static fn ($permission): string => $permission->value, $access->allowedPermissions());
            self::assertSame($expected, array_column($this->accesses[$name]['availablePermissions'], 'name'));
        }
        self::assertContains('plan_event_menu', array_column($this->accesses['events']['availablePermissions'], 'name'));
        self::assertContains('prepare_kitchen_meals', array_column($this->accesses['orders']['availablePermissions'], 'name'));
        self::assertNotContains('import', array_column($this->accesses['notes']['availablePermissions'], 'name'));
    }

    public function testAtomicReplaceIsIdempotentAndRejectsInvalidPayloadWithoutPartialChanges(): void
    {
        $selections = [$this->selection('notes', ['list', 'view']), $this->selection('events', ['view', 'plan_event_menu'])];
        $this->save($selections);
        $this->save($selections);
        $this->save(array_map(static fn (array $selection): array => ['accessUUID' => strtoupper($selection['accessUUID']), 'permissionsUUIDs' => array_map('strtoupper', $selection['permissionsUUIDs'])], $selections));
        $assigned = $this->assigned();
        self::assertCount(2, $assigned);
        foreach ($assigned as $access) {
            self::assertCount(2, $access['permissions']);
        }
        $this->save([$this->selection('notes', ['list', 'settings']), $this->selection('equipment', ['create'])], 422);
        self::assertSame($assigned, $this->assigned());
        $this->save([['accessUUID' => Uuid::uuid4()->toString(), 'permissionsUUIDs' => []]], 422);
        self::assertSame($assigned, $this->assigned());
        $this->save([$selections[0], $selections[0]], 422);
        self::assertSame($assigned, $this->assigned());
        $this->save([['accessUUID' => $this->accesses['notes']['uuid'], 'permissionsUUIDs' => ['not-a-uuid']]], 422);
        self::assertSame($assigned, $this->assigned());
        $this->save([$this->selection('notes', [])]);
        $assigned = $this->assigned();
        self::assertCount(1, $assigned);
        self::assertSame([], $assigned[0]['permissions']);
        $this->save([]);
        self::assertSame([], $this->assigned());
        $this->save($selections);
        self::assertCount(2, $this->assigned());
    }

    private function employeeLogin(): string
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $company = new Company();
        $company->setUUID(Uuid::uuid4()->toString());
        $company->setFullName('Firma testowa');
        $company->setShortName('Firma');
        $company->setNIP('1234567890');
        $company->setREGON('123456789');
        $company->setInternalCode('AUTH');
        $company->setActive(true);
        $company->setIndustry($manager->getRepository(Industry::class)->findOneBy([]));
        $department = new Department();
        $department->setUUID(Uuid::uuid4()->toString());
        $department->setName('Obsługa');
        $department->setInternalCode('AUTH');
        $department->setActive(true);
        $department->setCompany($company);
        $employee = new Employee();
        $employee->setUUID(Uuid::uuid4()->toString());
        $employee->setFirstName('Anna');
        $employee->setLastName('Testowa');
        $employee->setPESEL('00000000000');
        $employee->setInternalCode('AUTH');
        $employee->setEmploymentFrom(new \DateTime('2020-01-01'));
        $employee->setActive(true);
        $employee->setCompany($company);
        $employee->setDepartment($department);
        $employee->setPosition($manager->getRepository(Position::class)->findOneBy([]));
        $employee->setContractType($manager->getRepository(ContractType::class)->findOneBy([]));
        $employee->setRole($manager->find(Role::class, $this->roleUuid));
        $user = User::create('employee@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $user->setEmployee($employee);
        foreach ([$company, $department, $employee, $user] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $this->getAuthenticatedClient('employee@example.test', 'TestOnly123!');

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    public function testGrantedActionsAreEnforcedAndRevocationAppliesToExistingTokens(): void
    {
        $this->save([$this->selection('events', ['list']), $this->selection('notes', ['list'])]);
        $token = $this->employeeLogin();
        $this->api('GET', '/api/events');
        $this->api('POST', '/api/events', ['title' => 'Zabronione', 'date' => '2026-12-31'], 403);
        $this->api('GET', '/api/equipment', [], 403);
        $this->save([], 403);
        $context = $this->api('GET', '/api/auth/authorization');
        self::assertContains('events.list', $context['permissions']);
        self::assertNotContains('events.create', $context['permissions']);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->adminToken);
        $this->save([]);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$token);
        $this->api('GET', '/api/events', [], 403);
        self::assertSame([], $this->api('GET', '/api/auth/authorization')['permissions']);
    }

    public function testSeparateDelegatedRightsCannotChangeTheOtherPartOfTheAssignment(): void
    {
        $this->save([$this->selection('roles', ['list', 'view', 'assign_permission_to_access_role']), $this->selection('notes', ['list'])]);
        $token = $this->employeeLogin();
        $this->save([$this->selection('roles', ['list', 'view', 'assign_permission_to_access_role']), $this->selection('notes', ['list', 'view'])]);
        $this->save([$this->selection('roles', ['list', 'view', 'assign_permission_to_access_role'])], 403);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->adminToken);
        $this->save([$this->selection('roles', ['list', 'view', 'assign_access_to_role']), $this->selection('notes', ['list'])]);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$token);
        $this->save([$this->selection('roles', ['list', 'view', 'assign_access_to_role']), $this->selection('notes', ['list', 'view'])], 403);
        $this->save([$this->selection('roles', ['list', 'view', 'assign_access_to_role'])]);
        self::assertCount(1, $this->assigned());
    }

    public function testOrphanedPermissionAndInactiveAccessDoNotGrantAuthorization(): void
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $role = $manager->find(Role::class, $this->roleUuid);
        $role->rename('Superadministrator');
        $access = $manager->find(Access::class, $this->accesses['events']['uuid']);
        $permission = $manager->find(Permission::class, $this->permissions['list']);
        $manager->persist(new RoleAccessPermission($role, $access, $permission));
        $manager->flush();
        $this->employeeLogin();
        $this->api('GET', '/api/events', [], 403);
        self::assertSame([], $this->api('GET', '/api/auth/authorization')['permissions']);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$this->adminToken);
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->find(Access::class, $this->accesses['events']['uuid'])->setActive(false);
        $manager->flush();
        $this->save([$this->selection('events', ['list'])], 422);
        $manager = static::getContainer()->get('doctrine')->resetManager();
        $manager->find(Access::class, $this->accesses['events']['uuid'])->setActive(true);
        $manager->find(Permission::class, $this->permissions['list'])->setActive(false);
        $manager->flush();
        $this->save([$this->selection('notes', ['list'])], 422);
        $manager = static::getContainer()->get('doctrine')->resetManager();
        $manager->find(Access::class, $this->accesses['events']['uuid'])->getModule()->setActive(false);
        $manager->flush();
        $this->save([$this->selection('events', [])], 422);
    }
}
