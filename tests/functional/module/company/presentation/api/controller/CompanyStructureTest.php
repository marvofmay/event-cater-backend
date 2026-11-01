<?php

declare(strict_types=1);

namespace App\tests\functional\module\company\presentation\api\controller;

use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\Contact;
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

final class CompanyStructureTest extends FunctionalTestBase
{
    public function testStructureMigrationRepairsLegacySoftDeleteDefaults(): void
    {
        $ids = $this->fixtures();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $connection = $manager->getConnection();
        $role = $manager->find(Role::class, $ids['role']);
        $companies = $manager->getRepository(Access::class)->findOneBy(['name' => 'companies']);
        $list = $manager->getRepository(Permission::class)->findOneBy(['name' => 'list']);
        $manager->persist(new RoleAccess($role, $companies));
        $manager->persist(new RoleAccessPermission($role, $companies, $list));
        $manager->flush();
        $connection->executeStatement("DELETE FROM access WHERE name = 'company_structure'");
        foreach (['access', 'role_access', 'role_access_permission'] as $table) {
            $connection->executeStatement("ALTER TABLE $table MODIFY deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
        $migrationDirectory = dirname(__DIR__, 7).'/migrations/';
        require_once $migrationDirectory.'Version20261031090000.php';
        require_once $migrationDirectory.'Version20261031100000.php';
        foreach ([\DoctrineMigrations\Version20261031090000::class, \DoctrineMigrations\Version20261031100000::class] as $migrationClass) {
            $migration = new $migrationClass($connection, new \Psr\Log\NullLogger());
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
        }
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM access WHERE name = 'company_structure' AND deleted_at IS NULL"));
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM role_access ra JOIN access a ON a.uuid = ra.access_uuid WHERE ra.role_uuid = ? AND a.name = 'company_structure' AND ra.deleted_at IS NULL", [$ids['role']]));
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM role_access_permission rap JOIN access a ON a.uuid = rap.access_uuid JOIN permission p ON p.uuid = rap.permission_uuid WHERE rap.role_uuid = ? AND a.name = 'company_structure' AND p.name = 'view' AND rap.deleted_at IS NULL", [$ids['role']]));
        $this->getAuthenticatedClient('structure@example.test', 'TestOnly123!');
        $this->branch();
        $this->client->request('GET', '/api/auth/authorization');
        self::assertResponseIsSuccessful();
        $authorization = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('company_structure', $authorization['accesses']);
        self::assertContains('company_structure.view', $authorization['permissions']);
    }

    private function fixtures(): array
    {
        $this->getAuthenticatedClient();
        $manager = self::getContainer()->get('doctrine')->getManager();
        self::assertStringContainsString('_test', $manager->getConnection()->getDatabase());
        $company = new Company();
        $company->setUUID(Uuid::uuid4()->toString());
        $company->setFullName('Mapa testowa');
        $company->setShortName('Mapa');
        $company->setNIP('1234567890');
        $company->setREGON('123456789');
        $company->setInternalCode('TEST');
        $company->setActive(true);
        $company->setIndustry($manager->getRepository(Industry::class)->findOneBy([]));
        $subsidiary = clone $company;
        $subsidiary->setUUID(Uuid::uuid4()->toString());
        $subsidiary->setFullName('Spółka zależna');
        $subsidiary->setParentCompany($company);
        $department = new Department();
        $department->setUUID(Uuid::uuid4()->toString());
        $department->setName('Kuchnia');
        $department->setInternalCode('KUCHNIA');
        $department->setCompany($company);
        $department->setActive(true);
        $child = new Department();
        $child->setUUID(Uuid::uuid4()->toString());
        $child->setName('Cukiernia');
        $child->setInternalCode('CUK');
        $child->setCompany($company);
        $child->setParentDepartment($department);
        $child->setActive(false);
        $position = Position::create('Kucharz', null, true);
        $role = Role::create('Odczyt struktury');
        $employee = new Employee();
        $employee->setUUID(Uuid::uuid4()->toString());
        $employee->setFirstName('Anna');
        $employee->setLastName('Kowalska');
        $employee->setPESEL('11111111111');
        $employee->setInternalCode('TEST');
        $employee->setActive(true);
        $employee->setCompany($company);
        $employee->setDepartment($department);
        $employee->setPosition($position);
        $employee->setRole($role);
        $user = User::create('structure@example.test');
        $user->setPassword(password_hash('TestOnly123!', PASSWORD_BCRYPT));
        $employee->setUser($user);
        $email = new Contact();
        $email->setType('email');
        $email->setData('anna.structure@example.test');
        $employee->addContact($email);
        $phone = new Contact();
        $phone->setType('phone');
        $phone->setData('+48 123-456-789');
        $employee->addContact($phone);
        foreach ([$company, $subsidiary, $department, $child, $position, $role, $employee, $user] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();

        return ['company' => $company->getUUID()->toString(), 'department' => $department->getUUID()->toString(), 'child' => $child->getUUID()->toString(), 'role' => $role->getUUID()->toString()];
    }

    private function branch(array $parameters = [], int $status = 200): array
    {
        $this->client->request('GET', '/api/company-structure?'.http_build_query($parameters));
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'] ?? [];
    }

    public function testLazyHierarchyPagingSearchAndMinimalEmployeeData(): void
    {
        $ids = $this->fixtures();
        $root = $this->branch();
        self::assertSame(['Mapa testowa'], array_column($root['items'], 'name'));
        $parameters = ['parentType' => 'company', 'parentUuid' => $ids['company'], 'pageSize' => 1];
        $first = $this->branch($parameters);
        self::assertSame(2, $first['total']);
        self::assertTrue($first['hasMore']);
        self::assertSame('Spółka zależna', $first['items'][0]['name']);
        $second = $this->branch($parameters + ['page' => 2]);
        self::assertSame('Kuchnia', $second['items'][0]['name']);
        self::assertFalse($second['hasMore']);
        $department = $this->branch(['parentType' => 'department', 'parentUuid' => $ids['department']]);
        self::assertSame(['Anna Kowalska', 'Cukiernia'], array_column($department['items'], 'name'));
        self::assertFalse($department['items'][1]['active']);
        self::assertSame('Kucharz', $department['items'][0]['subtitle']);
        self::assertSame(['type', 'uuid', 'name', 'subtitle', 'active'], array_keys($department['items'][0]));
        $search = $this->branch(['parentType' => 'department', 'parentUuid' => $ids['department'], 'phrase' => 'Kucharz']);
        self::assertSame(1, $search['total']);
        self::assertSame('Anna Kowalska', $search['items'][0]['name']);
        self::assertSame(0, $this->branch(['phrase' => '%'])['total']);
        self::assertSame([], $this->branch(['parentType' => 'department', 'parentUuid' => $ids['child']])['items']);
    }

    public function testDeletedNodesAndInvalidBranchesAreNotExposed(): void
    {
        $ids = $this->fixtures();
        foreach ([['parentType' => 'invalid'], ['page' => 0], ['pageSize' => 1000], ['parentType' => 'department', 'parentUuid' => 'invalid'], ['phrase' => str_repeat('x', 101)], ['mode' => 'search', 'phrase' => 'a'], ['mode' => 'path', 'nodeType' => 'employee', 'nodeUuid' => 'invalid'], ['mode' => 'unknown']] as $parameters) {
            $this->branch($parameters, 400);
        }
        $this->branch(['parentType' => 'company', 'parentUuid' => Uuid::uuid4()->toString()], 404);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE department SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = ?', [$ids['department']]);
        $this->branch(['parentType' => 'department', 'parentUuid' => $ids['department']], 404);
        $company = $this->branch(['parentType' => 'company', 'parentUuid' => $ids['company']]);
        self::assertSame(['Spółka zależna', 'Cukiernia'], array_column($company['items'], 'name'));
        $connection->executeStatement('UPDATE company SET deleted_at = CURRENT_TIMESTAMP WHERE uuid = ?', [$ids['company']]);
        self::assertSame(['Spółka zależna'], array_column($this->branch()['items'], 'name'));
        $this->branch(['parentType' => 'department', 'parentUuid' => $ids['child']], 404);
        self::assertSame([], $this->branch(['mode' => 'search', 'phrase' => 'Anna'])['items']);
        $this->branch(['mode' => 'path', 'nodeType' => 'department', 'nodeUuid' => $ids['child']], 404);
    }

    public function testGlobalSearchFindsAllKindsAndRevealsDeepPathsWithoutLoadingBranches(): void
    {
        $ids = $this->fixtures();
        $employee = $this->branch(['mode' => 'search', 'phrase' => 'Anna'])['items'][0];
        self::assertSame('Anna Kowalska', $employee['name']);
        self::assertSame('employee', $employee['type']);
        self::assertSame('Mapa testowa', $employee['companyName']);
        self::assertSame('Kuchnia', $employee['departmentName']);
        self::assertArrayNotHasKey('pesel', $employee);
        self::assertSame('company', $this->branch(['mode' => 'search', 'phrase' => 'Mapa'])['items'][0]['type']);
        self::assertSame('department', $this->branch(['mode' => 'search', 'phrase' => 'Cukiernia'])['items'][0]['type']);
        $first = $this->branch(['mode' => 'search', 'phrase' => 'Ku', 'pageSize' => 1]);
        $second = $this->branch(['mode' => 'search', 'phrase' => 'Ku', 'pageSize' => 1, 'page' => 2]);
        self::assertSame(2, $first['total']);
        self::assertTrue($first['hasMore']);
        self::assertFalse($second['hasMore']);
        self::assertNotSame($first['items'][0]['uuid'], $second['items'][0]['uuid']);
        self::assertSame([], $this->branch(['mode' => 'search', 'phrase' => '%%'])['items']);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE employee SET department_uuid = ? WHERE uuid = ?', [$ids['child'], $employee['uuid']]);
        $path = $this->branch(['mode' => 'path', 'nodeType' => 'employee', 'nodeUuid' => $employee['uuid']]);
        self::assertSame(['Mapa testowa', 'Kuchnia', 'Cukiernia', 'Anna Kowalska'], array_column($path['items'], 'name'));
        self::assertSame(['type', 'uuid', 'name', 'subtitle', 'active'], array_keys($path['items'][3]));
        $companyPath = $this->branch(['mode' => 'path', 'nodeType' => 'company', 'nodeUuid' => $ids['company']]);
        self::assertSame(['Mapa testowa'], array_column($companyPath['items'], 'name'));
        $this->branch(['mode' => 'path', 'nodeType' => 'employee', 'nodeUuid' => Uuid::uuid4()->toString()], 404);
    }

    public function testStructureAccessIsSeparateFromCatalogsAndPreviewExposesOnlyContactData(): void
    {
        $ids = $this->fixtures();
        $this->getAuthenticatedClient('structure@example.test', 'TestOnly123!');
        $this->branch([], 403);
        foreach (['companies', 'company_structure'] as $name) {
            $manager = self::getContainer()->get('doctrine')->getManager();
            $role = $manager->find(Role::class, $ids['role']);
            $access = $manager->getRepository(Access::class)->findOneBy(['name' => $name]);
            $permission = $manager->getRepository(Permission::class)->findOneBy(['name' => $name === 'company_structure' ? 'view' : 'list']);
            $manager->persist(new RoleAccess($role, $access));
            $manager->persist(new RoleAccessPermission($role, $access, $permission));
            $manager->flush();
            if ('companies' === $name) {
                $this->branch([], 403);
                continue;
            }
            $search = $this->branch(['mode' => 'search', 'phrase' => 'Anna']);
            self::assertSame(1, $search['total']);
            $employeeUuid = $search['items'][0]['uuid'];
            self::assertSame(2, $this->branch(['parentType' => 'department', 'parentUuid' => $ids['department']])['total']);
            $this->client->request('GET', '/api/company-structure/employees/'.$employeeUuid);
            self::assertResponseIsSuccessful();
            $preview = json_decode($this->client->getResponse()->getContent(), true)['data'];
            self::assertSame(['uuid', 'firstName', 'lastName', 'phones', 'emails'], array_keys($preview));
            self::assertSame(['+48 123-456-789'], $preview['phones']);
            self::assertSame(['anna.structure@example.test'], $preview['emails']);
            $this->client->request('GET', '/api/preferences/companies');
            self::assertResponseIsSuccessful();
            $this->client->jsonRequest('PUT', '/api/preferences/companies', ['scope' => 'user', 'version' => 0, 'pageSize' => 25]);
            self::assertResponseIsSuccessful();
            $this->client->jsonRequest('PUT', '/api/preferences/companies', ['scope' => 'tenant', 'version' => 0, 'pageSize' => 50]);
            self::assertResponseStatusCodeSame(403);
            $this->client->request('GET', '/api/employees');
            self::assertResponseStatusCodeSame(403);
            $this->client->request('GET', '/api/company-structure/employees/'.Uuid::uuid4());
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testGlobalSearchMatchesContactsAndNormalizesPhoneFormatting(): void
    {
        $ids = $this->fixtures();
        foreach (['anna.structure@example.test', 'ANNA.STRUCTURE@EXAMPLE.TEST', 'structure@', '123456789', '123 456 789', '+48 (123) 456-789', '456789'] as $phrase) {
            $result = $this->branch(['mode' => 'search', 'phrase' => $phrase]);
            self::assertSame(1, $result['total'], $phrase);
            self::assertSame('Anna Kowalska', $result['items'][0]['name']);
            self::assertArrayNotHasKey('email', $result['items'][0]);
            self::assertArrayNotHasKey('phone', $result['items'][0]);
        }
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE contact SET active = 0 WHERE type = ?', ['phone']);
        self::assertSame(0, $this->branch(['mode' => 'search', 'phrase' => '123456789'])['total']);
        $connection->executeStatement('UPDATE contact SET deleted_at = CURRENT_TIMESTAMP WHERE type = ?', ['email']);
        self::assertSame(0, $this->branch(['mode' => 'search', 'phrase' => 'anna.structure@example.test'])['total']);
        $manager = self::getContainer()->get('doctrine')->getManager();
        foreach (['company', 'department'] as $type) {
            $contact = new Contact();
            $contact->setType('email');
            $contact->setData($type.'@contact.example.test');
            if ('company' === $type) {
                $contact->setCompany($manager->find(Company::class, $ids['company']));
            } else {
                $contact->setDepartment($manager->find(Department::class, $ids['department']));
            }
            $manager->persist($contact);
        }
        $manager->flush();
        self::assertSame('company', $this->branch(['mode' => 'search', 'phrase' => 'company@contact'])['items'][0]['type']);
        self::assertSame('department', $this->branch(['mode' => 'search', 'phrase' => 'department@contact'])['items'][0]['type']);
    }
}
