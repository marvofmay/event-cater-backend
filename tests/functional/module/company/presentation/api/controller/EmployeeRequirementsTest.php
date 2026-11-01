<?php

declare(strict_types=1);

namespace App\tests\functional\module\company\presentation\api\controller;

use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PESEL;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\ContractType;
use App\Module\Company\Domain\Entity\Industry;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Company\Domain\Entity\Role;
use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Enum\EmployeeImportColumnEnum;
use App\Module\Company\Domain\Service\Employee\ImportEmployeesFromXLSX;
use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use App\tests\functional\FunctionalTestBase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\SerializerInterface;

final class EmployeeRequirementsTest extends FunctionalTestBase
{
    public function testSupervisorCanBeFromAnotherDepartmentIsOptionalAndCannotCreateCycles(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $managerUuid = $connection->fetchOne('SELECT uuid FROM employee WHERE pesel = ?', [$details['pesel']]);
        $this->client->jsonRequest('POST', '/api/departments', ['name' => 'Logistyka testowa', 'internalCode' => 'LOG', 'companyUUID' => $details['companyUUID'], 'address' => ['city' => 'Warszawa', 'street' => 'Testowa 1', 'postcode' => '00-001', 'country' => 'Polska']]);
        self::assertResponseStatusCodeSame(201);
        $department = $connection->fetchOne("SELECT uuid FROM department WHERE internal_code = 'LOG'");
        $subordinate = array_replace($details, ['firstName' => 'Pracownik', 'email' => 'worker@example.test', 'pesel' => '22222222222', 'phones' => ['987654321'], 'departmentUUID' => $department, 'parentEmployeeUUID' => $managerUuid]);
        $this->client->jsonRequest('POST', '/api/employees', $subordinate);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $subordinateUuid = $connection->fetchOne('SELECT uuid FROM employee WHERE pesel = ?', [$subordinate['pesel']]);
        self::assertSame($managerUuid, $connection->fetchOne('SELECT employee_uuid FROM employee WHERE uuid = ?', [$subordinateUuid]));
        $this->client->request('GET', '/api/employees/parent-options?'.http_build_query(['companyUUID' => $details['companyUUID'], 'employeeUUID' => $subordinateUuid]));
        self::assertResponseIsSuccessful();
        $options = json_decode($this->client->getResponse()->getContent(), true)['data'];
        self::assertContains($managerUuid, array_column($options, 'uuid'));
        self::assertNotContains($subordinateUuid, array_column($options, 'uuid'));
        $this->client->jsonRequest('PUT', '/api/employees/'.$managerUuid, array_replace($details, ['parentEmployeeUUID' => $subordinateUuid]));
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('PUT', '/api/employees/'.$subordinateUuid, array_replace($subordinate, ['parentEmployeeUUID' => null]));
        self::assertResponseIsSuccessful();
        self::assertNull($connection->fetchOne('SELECT employee_uuid FROM employee WHERE uuid = ?', [$subordinateUuid]));
    }

    public function testXlsxSupervisorCanFollowSubordinateAndInvalidHierarchyIsRejected(): void
    {
        $details = $this->details();
        $managerRow = ['Kierownik', null, '11111111111', 'boss@example.test', '123456789', null, null, null, null, null, $details['departmentUUID'], $details['positionUUID'], null, $details['roleUUID'], null, null, null, null, 1, 1];
        $workerRow = $managerRow;
        $workerRow[0] = 'Pracownik';
        $workerRow[2] = '22222222222';
        $workerRow[3] = 'worker@example.test';
        $workerRow[4] = '987654321';
        $workerRow[14] = '11111111111';
        $path = $this->spreadsheet([$workerRow, $managerRow]);
        try {
            $manager = self::getContainer()->get('doctrine')->getManager();
            $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com']);
            $file = File::create('supervisors.xlsx', $path, 'xlsx', 'import', $user);
            $import = Import::create(ImportKindEnum::IMPORT_EMPLOYEES, ImportStatusEnum::PENDING, $file, $user);
            $manager->persist($file);
            $manager->persist($import);
            $manager->flush();
            $importer = self::getContainer()->get(ImportEmployeesFromXLSX::class);
            $importer->setFilePath($path);
            self::assertSame([], $importer->validateBeforeImport());
            $importer->run($import, $user);
            self::assertSame(ImportStatusEnum::DONE, $import->getStatus());
            self::assertSame('11111111111', $manager->getConnection()->fetchOne('SELECT parent.pesel FROM employee child JOIN employee parent ON parent.uuid = child.employee_uuid WHERE child.pesel = ?', ['22222222222']));
            foreach (['22222222222', '33333333333'] as $parentPesel) {
                $managerRow[14] = $parentPesel;
                $invalid = $this->spreadsheet([$workerRow, $managerRow]);
                try {
                    $importer->setFilePath($invalid);
                    self::assertNotEmpty($importer->validateBeforeImport());
                } finally { unlink($invalid); }
            }
        } finally { unlink($path); }
    }

    public function testEmployeeCanUploadAvatarAndSelectDefaultAvatar(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE user SET must_change_password = 0 WHERE login_phone = ?', ['48123456789']);
        $userUuid = $connection->fetchOne('SELECT uuid FROM user WHERE login_phone = ?', ['48123456789']);
        $this->getAuthenticatedClient('123456789', $details['pesel']);
        $temporary = tempnam(sys_get_temp_dir(), 'avatar-');
        file_put_contents($temporary, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
        $directory = self::getContainer()->getParameter('public_upload_path').'/avatars/'.$userUuid;
        try {
            $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($temporary, 'avatar.png', 'image/png', null, true);
            $this->client->request('PATCH', '/api/me/avatar', ['type' => 'custom'], ['uploadedFile' => $file]);
            self::assertResponseIsSuccessful();
            $this->client->request('GET', '/api/me/data');
            self::assertResponseIsSuccessful();
            $avatar = json_decode($this->client->getResponse()->getContent(), true)['data']['employee']['avatar'];
            self::assertSame('custom', $avatar['avatarType']);
            self::assertStringStartsWith('avatars/'.$userUuid.'/', $avatar['avatarPath']);
            self::assertFileExists(self::getContainer()->getParameter('public_upload_path').'/'.$avatar['avatarPath']);
            $this->client->request('GET', '/api/me/avatar');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
            $headerAvatar = json_decode($this->client->getResponse()->getContent(), true)['data'];
            self::assertSame($avatar, $headerAvatar);
            $employeeUuid = $connection->fetchOne('SELECT uuid FROM employee WHERE pesel = ?', [$details['pesel']]);
            $this->getAuthenticatedClient();
            $this->client->request('GET', '/api/employees');
            self::assertResponseIsSuccessful();
            $employees = array_column(json_decode($this->client->getResponse()->getContent(), true)['data']['items'], null, 'uuid');
            self::assertSame($avatar, $employees[$employeeUuid]['avatar']);
            $this->getAuthenticatedClient('123456789', $details['pesel']);
            $this->client->request('PATCH', '/api/me/avatar', ['type' => 'default', 'defaultAvatar' => 'woman']);
            self::assertResponseIsSuccessful();
            $this->client->request('GET', '/api/me/data');
            $avatar = json_decode($this->client->getResponse()->getContent(), true)['data']['employee']['avatar'];
            self::assertSame('default', $avatar['avatarType']);
            self::assertSame('woman', $avatar['defaultAvatar']);
            self::assertNull($avatar['avatarPath']);
            $this->client->request('GET', '/api/me/avatar');
            self::assertResponseIsSuccessful();
            self::assertSame($avatar, json_decode($this->client->getResponse()->getContent(), true)['data']);
            $this->getAuthenticatedClient();
            $this->client->request('GET', '/api/employees');
            self::assertResponseIsSuccessful();
            $employees = array_column(json_decode($this->client->getResponse()->getContent(), true)['data']['items'], null, 'uuid');
            self::assertSame($avatar, $employees[$employeeUuid]['avatar']);
        } finally {
            if (is_file($temporary)) unlink($temporary);
            foreach (glob($directory.'/*') ?: [] as $path) unlink($path);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $ipLimiter = self::getContainer()->get('limiter.password_reset_ip');
        $ipLimiter->create('127.0.0.1')->reset();
        $ipLimiter->create('confirm:127.0.0.1')->reset();
        $tenantUuid = self::getContainer()->get(\App\Module\Saas\Infrastructure\TenantContext::class)->current()['uuid'];
        foreach (['anna@example.test', 'unknown@example.test', '48123456789'] as $login) {
            self::getContainer()->get('limiter.password_reset_account')->create(hash('sha256', $tenantUuid.'|'.$login))->reset();
        }
    }

    private function recovery(array &$messages): \App\Module\System\Domain\Service\User\PasswordRecoveryService
    {
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);
        $mailer->method('send')->willReturnCallback(static function ($message) use (&$messages): void { $messages[] = $message; });

        return new \App\Module\System\Domain\Service\User\PasswordRecoveryService(
            self::getContainer()->get('doctrine')->getConnection(),
            self::getContainer()->get(\App\Module\Saas\Infrastructure\TenantContext::class),
            $mailer,
            self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class),
            'https://app.example.test/reset-password',
        );
    }

    public function testPasswordRecoveryWorksForInitialAndPersonalPasswordsAndInvalidatesOldSessions(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $password = $details['pesel'];
        $messages = [];
        $recovery = $this->recovery($messages);
        foreach (['NoweHaslo1!', 'KolejneHaslo2!'] as $newPassword) {
            $this->getAuthenticatedClient('123456789', $password);
            $oldToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
            $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
            preg_match('/#token=([a-f0-9]{64})/', end($messages)->getTextBody(), $match);
            self::assertNotEmpty($match);
            $token = $match[1];
            $connection = self::getContainer()->get('doctrine')->getConnection();
            $tenantUuid = self::getContainer()->get(\App\Module\Saas\Infrastructure\TenantContext::class)->current()['uuid'];
            self::assertSame(hash('sha256', $tenantUuid.'|'.$token), $connection->fetchOne('SELECT token_hash FROM password_reset_request'));
            $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $token, 'newPassword' => 'weak', 'confirmation' => 'weak']);
            self::assertResponseStatusCodeSame(422);
            $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $token, 'newPassword' => $newPassword, 'confirmation' => $newPassword]);
            self::assertResponseIsSuccessful();
            self::assertArrayNotHasKey('token', json_decode($this->client->getResponse()->getContent(), true));
            $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$oldToken);
            $this->client->request('GET', '/api/preferences');
            self::assertResponseStatusCodeSame(401);
            $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $token, 'newPassword' => $newPassword, 'confirmation' => $newPassword]);
            self::assertResponseStatusCodeSame(422);
            $this->getAuthenticatedClient('123456789', $newPassword);
            $claims = json_decode(base64_decode(explode('.', json_decode($this->client->getResponse()->getContent(), true)['token'])[1]), true);
            self::assertFalse($claims['mustChangePassword']);
            $password = $newPassword;
            $recovery = $this->recovery($messages);
        }
    }

    public function testPasswordRecoveryRequestDoesNotDiscloseAccountsAndQueuesOnlyAnIdentifier(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer invalid');
        foreach (['anna@example.test', 'unknown@example.test'] as $login) {
            $this->client->jsonRequest('POST', '/api/auth/password-reset/request', ['login' => $login]);
            self::assertResponseStatusCodeSame(202);
            self::assertSame(\App\Module\System\Presentation\API\Controller\Auth\PasswordRecoveryController::ACCEPTED, json_decode($this->client->getResponse()->getContent(), true)['message']);
        }
        $queued = self::getContainer()->get('messenger.transport.password_reset')->getSent();
        self::assertCount(1, $queued);
        self::assertInstanceOf(\App\Module\System\Application\Command\Auth\RequestPasswordResetCommand::class, $queued[0]->getMessage());
        self::assertFalse(property_exists($queued[0]->getMessage(), 'token'));
        $messages = [];
        $recovery = $this->recovery($messages);
        foreach (['unknown@example.test', 'admin.ec@gmail.com'] as $login) {
            $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand($login, Uuid::uuid4()->toString(), time()));
        }
        self::assertSame([], $messages);
        self::assertSame(0, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM password_reset_request'));
    }

    public function testPasswordRecoveryRejectsExpiredAndChangedIdentityTokens(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $messages = [];
        $recovery = $this->recovery($messages);
        $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
        preg_match('/#token=([a-f0-9]{64})/', end($messages)->getTextBody(), $match);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('UPDATE password_reset_request SET expires_at = ?', ['2000-01-01 00:00:00']);
        $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $match[1], 'newPassword' => 'NoweHaslo1!', 'confirmation' => 'NoweHaslo1!']);
        self::assertResponseStatusCodeSame(422);
        $connection->executeStatement('UPDATE password_reset_request SET expires_at = ?, email = ?', [gmdate('Y-m-d H:i:s', time() + 900), 'changed@example.test']);
        $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $match[1], 'newPassword' => 'NoweHaslo1!', 'confirmation' => 'NoweHaslo1!']);
        self::assertResponseStatusCodeSame(422);
        $connection->executeStatement('UPDATE password_reset_request SET email = ?, credential_version = credential_version + 1', [$details['email']]);
        $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $match[1], 'newPassword' => 'NoweHaslo1!', 'confirmation' => 'NoweHaslo1!']);
        self::assertResponseStatusCodeSame(422);
        $connection->executeStatement('UPDATE password_reset_request SET credential_version = credential_version - 1, token_hash = ?', [hash('sha256', 'another-tenant|'.$match[1])]);
        $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $match[1], 'newPassword' => 'NoweHaslo1!', 'confirmation' => 'NoweHaslo1!']);
        self::assertResponseStatusCodeSame(422);
        $this->getAuthenticatedClient('123456789', $details['pesel']);
    }

    public function testPasswordRecoveryDoesNotSendForLegacyMissingEmailOrInactiveEmployee(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $messages = [];
        $recovery = $this->recovery($messages);
        $connection->executeStatement('UPDATE user SET email = NULL WHERE login_phone = ?', ['48123456789']);
        $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
        self::assertSame([], $messages);
        $connection->executeStatement('UPDATE user SET email = ? WHERE login_phone = ?', [$details['email'], '48123456789']);
        $connection->executeStatement('UPDATE employee SET active = 0 WHERE pesel = ?', [$details['pesel']]);
        $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
        self::assertSame([], $messages);
    }

    public function testPasswordRecoveryCooldownAndNewerLinkInvalidatePreviousLink(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $messages = [];
        $recovery = $this->recovery($messages);
        $command = new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time());
        $recovery->issue($command);
        $recovery->issue($command);
        $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
        self::assertCount(1, $messages);
        preg_match('/#token=([a-f0-9]{64})/', $messages[0]->getTextBody(), $match);
        self::getContainer()->get('doctrine')->getConnection()->executeStatement('UPDATE password_reset_request SET created_at = ?', [gmdate('Y-m-d H:i:s', time() - 61)]);
        $recovery->issue(new \App\Module\System\Application\Command\Auth\RequestPasswordResetCommand('48123456789', Uuid::uuid4()->toString(), time()));
        self::assertCount(2, $messages);
        $this->client->jsonRequest('POST', '/api/auth/password-reset/confirm', ['token' => $match[1], 'newPassword' => 'NoweHaslo1!', 'confirmation' => 'NoweHaslo1!']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testProvisioningExistingEmployeesIsExplicitIdempotentAndPreservesExistingPasswords(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $employee = $connection->fetchOne('SELECT uuid FROM employee WHERE pesel = ?', [$details['pesel']]);
        $connection->executeStatement('DELETE FROM user WHERE employee_uuid = ?', [$employee]);
        $oldPasswords = $connection->fetchAllKeyValue('SELECT uuid, password FROM user');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:employees:provision-accounts'));
        self::assertSame(2, $tester->execute([]));
        self::assertSame(0, $tester->execute(['--tenant' => 'default']));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE employee_uuid = ?', [$employee]));
        self::assertSame(0, $tester->execute(['--tenant' => 'default', '--execute' => true]));
        $account = $connection->fetchAssociative('SELECT password, must_change_password, login_phone FROM user WHERE employee_uuid = ?', [$employee]);
        self::assertSame('48123456789', $account['login_phone']);
        self::assertSame(1, (int) $account['must_change_password']);
        self::assertTrue(password_verify($details['pesel'], $account['password']));
        self::assertSame(0, $tester->execute(['--tenant' => 'default', '--execute' => true]));
        self::assertStringContainsString('0 brakujących kont', $tester->getDisplay());
        foreach ($oldPasswords as $uuid => $password) {
            self::assertSame($password, $connection->fetchOne('SELECT password FROM user WHERE uuid = ?', [$uuid]));
        }
        self::assertSame($account['password'], $connection->fetchOne('SELECT password FROM user WHERE employee_uuid = ?', [$employee]));
        $this->getAuthenticatedClient('123456789', $details['pesel']);
        $this->client->request('GET', '/api/preferences');
        self::assertResponseStatusCodeSame(423);
    }

    public function testPhoneLoginRequiresPasswordChangeAndRevokesInitialToken(): void
    {
        $details = $this->details();
        $this->client->request('GET', '/api/accesses');
        $accesses = array_column(json_decode($this->client->getResponse()->getContent(), true)['data'], null, 'name');
        $this->client->request('GET', '/api/permissions');
        $permissions = array_column(json_decode($this->client->getResponse()->getContent(), true)['data'], 'uuid', 'name');
        $this->client->jsonRequest('PUT', '/api/roles/'.$details['roleUUID'].'/accesses', ['accesses' => [['accessUUID' => $accesses['notes']['uuid'], 'permissionsUUIDs' => [$permissions['list']]]]]);
        self::assertResponseIsSuccessful();
        $details['phones'] = ['+48 123-456-789'];
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $account = $connection->fetchAssociative('SELECT u.* FROM user u JOIN employee e ON e.uuid = u.employee_uuid WHERE e.pesel = ?', [$details['pesel']]);
        self::assertSame($details['email'], $account['email']);
        self::assertSame('48123456789', $account['login_phone']);
        self::assertTrue(password_verify($details['pesel'], $account['password']));
        self::assertNotSame($details['pesel'], $account['password']);
        $this->getAuthenticatedClient('123456789', $details['pesel']);
        $initialToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
        $claims = json_decode(base64_decode(explode('.', $initialToken)[1]), true);
        self::assertTrue($claims['mustChangePassword']);
        self::assertSame([], $claims['permissions']);
        foreach (['/api/users/notes', '/api/preferences', '/api/auth/authorization', '/api/employees'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(423, $this->client->getResponse()->getContent());
        }
        $newPassword = 'WlasneHaslo2026!Nowe';
        $this->client->jsonRequest('POST', '/api/auth/change-password', ['currentPassword' => 'incorrect', 'newPassword' => $newPassword, 'confirmation' => $newPassword]);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/auth/change-password', ['currentPassword' => $details['pesel'], 'newPassword' => 'weak', 'confirmation' => 'weak']);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', '/api/auth/change-password', ['currentPassword' => $details['pesel'], 'newPassword' => $newPassword, 'confirmation' => $newPassword]);
        self::assertResponseIsSuccessful();
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];
        $claims = json_decode(base64_decode(explode('.', $token)[1]), true);
        self::assertFalse($claims['mustChangePassword']);
        self::assertSame(['notes.list'], $claims['permissions']);
        self::assertSame(['notes'], $claims['modules']);
        $this->client->request('GET', '/api/users/notes');
        self::assertResponseStatusCodeSame(401);
        $this->client->setServerParameter('HTTP_Authorization', 'Bearer '.$token);
        $this->client->request('GET', '/api/users/notes?userUUID='.$account['uuid']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/me/data');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/employees');
        self::assertResponseStatusCodeSame(403);
        $this->getAuthenticatedClient('0048 123 456 789', $newPassword);
        $this->client->jsonRequest('POST', '/api/login', ['email' => '123456789', 'password' => $details['pesel']]);
        self::assertResponseStatusCodeSame(401);
        $connection->executeStatement('UPDATE employee SET active = 0 WHERE pesel = ?', [$details['pesel']]);
        $this->client->jsonRequest('POST', '/api/login', ['email' => '123456789', 'password' => $newPassword]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testEmployeeLoginPhoneMustBeValidAndUniqueWithinCompany(): void
    {
        $details = $this->details();
        foreach (['abc', '123', '+48', ''] as $phone) {
            $this->client->jsonRequest('POST', '/api/employees', array_replace($details, ['phones' => [$phone]]));
            self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());
        }
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201);
        $this->client->jsonRequest('POST', '/api/employees', array_replace($details, ['email' => 'other@example.test', 'pesel' => '22222222222', 'phones' => ['+48 123-456-789']]));
        self::assertResponseStatusCodeSame(409, $this->client->getResponse()->getContent());
    }

    private function details(): array
    {
        $this->getAuthenticatedClient();
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $manager = self::getContainer()->get('doctrine')->getManager();
        $company = new Company();
        $company->setUUID(Uuid::uuid4()->toString());
        $company->setFullName('Firma testowa');
        $company->setShortName('Firma');
        $company->setNIP('1234567890');
        $company->setREGON('123456789');
        $company->setInternalCode('COMPANY');
        $company->setActive(true);
        $company->setIndustry($manager->getRepository(Industry::class)->findOneBy([]));
        $manager->persist($company);
        $manager->flush();
        $companyUuid = $company->getUUID()->toString();
        $references = [];
        foreach (['positionUUID' => Position::class, 'contractTypeUUID' => ContractType::class, 'roleUUID' => Role::class] as $key => $entity) {
            $references[$key] = $manager->getRepository($entity)->findOneBy([])->getUUID()->toString();
        }
        $this->client->jsonRequest('POST', '/api/departments', [
            'name' => 'Departament testowy', 'internalCode' => 'TEST', 'companyUUID' => $companyUuid,
            'address' => ['street' => 'Testowa 1', 'postcode' => '00-001', 'city' => 'Warszawa', 'country' => 'Polska'],
        ]);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $departmentUuid = self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT uuid FROM department WHERE internal_code = ?', ['TEST']);
        return $references + [
            'companyUUID' => $companyUuid, 'departmentUUID' => $departmentUuid,
            'firstName' => 'Anna', 'pesel' => '11111111111', 'isPeselFake' => true,
            'email' => 'anna@example.test', 'phones' => ['123456789'], 'employmentFrom' => '2026-10-24',
        ];
    }

    public function testCreateUpdateAndPreviewWithFakePeselAndOptionalPersonalData(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('POST', '/api/employees', $details);
        self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
        $connection = self::getContainer()->get('doctrine')->getConnection();
        $employee = $connection->fetchAssociative('SELECT uuid, last_name, is_pesel_fake FROM employee WHERE pesel = ?', [$details['pesel']]);
        self::assertSame('', $employee['last_name']);
        self::assertSame(1, (int) $employee['is_pesel_fake']);
        $this->client->request('GET', '/api/employees');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"isPeselFake":true', $this->client->getResponse()->getContent());
        $details['address'] = ['city' => 'Warszawa'];
        $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], $details);
        self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $fake = PESEL::fromString('11111111111', true);
        $restored = $serializer->deserialize($serializer->serialize($fake, 'json'), PESEL::class, 'json');
        self::assertTrue($restored->isFake());
        self::assertSame('11111111111', $restored->getValue());
        self::assertFalse($serializer->deserialize('"44051401458"', PESEL::class, 'json')->isFake());
    }

    public function testGeneratedFakePeselUsesSharedValidationRules(): void
    {
        $this->getAuthenticatedClient();
        $numbers = [];
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->client->jsonRequest('POST', '/api/employees/generate-fake-pesel', []);
            self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
            $data = json_decode($this->client->getResponse()->getContent(), true);
            self::assertTrue($data['isPeselFake']);
            self::assertNull(\App\Common\Shared\Utils\PESELValidator::validate($data['pesel']));
            self::assertTrue(checkdate((int) substr($data['pesel'], 2, 2), (int) substr($data['pesel'], 4, 2), 1900 + (int) substr($data['pesel'], 0, 2)));
            $numbers[] = $data['pesel'];
        }
        self::assertCount(20, array_unique($numbers));
    }

    public function testEmploymentStartCanBeOmittedNullOrEmptyAndClearedOnUpdate(): void
    {
        $details = $this->details();
        unset($details['employmentFrom']);
        foreach ([[], ['employmentFrom' => null], ['employmentFrom' => '']] as $index => $changes) {
            $payload = array_replace($details, $changes, [
                'pesel' => '1111111111'.$index,
                'email' => 'employee'.$index.'@example.test',
                'phones' => ['12345678'.$index],
                'employmentTo' => '2027-01-01',
            ]);
            $this->client->jsonRequest('POST', '/api/employees', $payload);
            self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
            $connection = self::getContainer()->get('doctrine')->getConnection();
            $employee = $connection->fetchAssociative('SELECT uuid, employment_from FROM employee WHERE pesel = ?', [$payload['pesel']]);
            self::assertNull($employee['employment_from']);
            foreach (['2026-10-24', null, '2026-10-25', ''] as $date) {
                $payload['employmentFrom'] = $date;
                $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], $payload);
                self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
                self::assertSame($date ?: null, $connection->fetchOne('SELECT employment_from FROM employee WHERE uuid = ?', [$employee['uuid']]));
            }
        }
        $this->client->request('GET', '/api/employees');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"employmentFrom":null', $this->client->getResponse()->getContent());
    }

    public function testProvidedEmploymentDatesStillRequireValidFormatAndRange(): void
    {
        $details = $this->details();
        foreach ([['employmentFrom' => 'invalid'], ['employmentFrom' => '2026-02-30'], ['employmentTo' => '2026-10-23']] as $changes) {
            $this->client->jsonRequest('POST', '/api/employees', array_replace($details, $changes));
            self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());
        }
    }

    public function testRequiredPhoneAndPeselCannotBeEmptyAndProvidedEmailMustBeValid(): void
    {
        $details = $this->details();
        foreach ([['phones' => null], ['phones' => []], ['phones' => ['   ']], ['email' => 'invalid'], ['pesel' => ''], ['contractTypeUUID' => 'invalid']] as $changes) {
            $this->client->jsonRequest('POST', '/api/employees', array_replace($details, $changes));
            self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());
        }
    }

    public function testEmailIsRequiredAndContractTypeRemainsOptional(): void
    {
        $details = $this->details();
        $contractTypeUuid = $details['contractTypeUUID'];
        unset($details['contractTypeUUID']);
        $missing = $details;
        unset($missing['email']);
        foreach ([$missing, array_replace($details, ['email' => null]), array_replace($details, ['email' => '']), array_replace($details, ['email' => '   '])] as $invalid) {
            $this->client->jsonRequest('POST', '/api/employees', $invalid);
            self::assertResponseStatusCodeSame(422);
        }
        foreach ([[], ['contractTypeUUID' => null], ['contractTypeUUID' => '']] as $index => $changes) {
            $payload = array_replace($details, $changes, ['email' => 'required'.$index.'@example.test', 'pesel' => '2222222222'.$index, 'phones' => ['12345678'.$index]]);
            $this->client->jsonRequest('POST', '/api/employees', $payload);
            self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
            $connection = self::getContainer()->get('doctrine')->getConnection();
            $employee = $connection->fetchAssociative('SELECT uuid, contract_type_uuid FROM employee WHERE pesel = ?', [$payload['pesel']]);
            self::assertNull($employee['contract_type_uuid']);
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE employee_uuid = ?', [$employee['uuid']]));
            $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], $payload);
            self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
            $payload['email'] = 'optional'.$index.'@example.test';
            $payload['contractTypeUUID'] = $contractTypeUuid;
            $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], $payload);
            self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
            self::assertSame($payload['email'], $connection->fetchOne('SELECT email FROM user WHERE employee_uuid = ?', [$employee['uuid']]));
            self::assertSame($contractTypeUuid, $connection->fetchOne('SELECT contract_type_uuid FROM employee WHERE uuid = ?', [$employee['uuid']]));
            $payload['contractTypeUUID'] = null;
            $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], $payload);
            self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
            self::assertNull($connection->fetchOne('SELECT contract_type_uuid FROM employee WHERE uuid = ?', [$employee['uuid']]));
            self::assertSame('optional'.$index.'@example.test', $connection->fetchOne('SELECT email FROM user WHERE employee_uuid = ?', [$employee['uuid']]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM contact WHERE employee_uuid = ? AND type = ?', [$employee['uuid'], 'email']));
            $this->client->jsonRequest('PUT', '/api/employees/'.$employee['uuid'], array_replace($payload, ['email' => null]));
            self::assertResponseStatusCodeSame(422);
            $this->client->jsonRequest('POST', '/api/employees', array_replace($details, [
                'pesel' => '3333333333'.$index,
                'email' => 'optional'.$index.'@example.test',
            ]));
            self::assertResponseStatusCodeSame(409, $this->client->getResponse()->getContent());
        }
        $this->client->request('GET', '/api/employees?includes=contractType,contacts');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"contractType":null', $this->client->getResponse()->getContent());
    }

    public function testDepartmentCanBeDeletedWithTheUpdatedEmployeeMapping(): void
    {
        $details = $this->details();
        $this->client->jsonRequest('DELETE', '/api/departments/'.$details['departmentUUID']);
        self::assertResponseStatusCodeSame(200, $this->client->getResponse()->getContent());
        self::assertNotEmpty(self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT deleted_at FROM department WHERE uuid = ?', [$details['departmentUUID']]));
    }

    private function spreadsheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'employee-').'.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([array_map(static fn (EmployeeImportColumnEnum $column) => $column->label(), array_filter(EmployeeImportColumnEnum::cases(), static fn (EmployeeImportColumnEnum $column) => $column->value < 100))], null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $path;
    }

    public function testRealXlsxCreatesAndUpdatesFakeEmployeeWithoutSurnameOrAddress(): void
    {
        $details = $this->details();
        $row = ['Anna', null, '11111111111', 'anna@example.test', '123456789', null, null, null, null, null, $details['departmentUUID'], $details['positionUUID'], null, $details['roleUUID'], null, null, null, null, 1, 1];
        $path = $this->spreadsheet([$row]);
        try {
            $manager = self::getContainer()->get('doctrine')->getManager();
            $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com']);
            $file = File::create('employees.xlsx', $path, 'xlsx', 'import', $user);
            $import = Import::create(ImportKindEnum::IMPORT_EMPLOYEES, ImportStatusEnum::PENDING, $file, $user);
            $manager->persist($file);
            $manager->persist($import);
            $manager->flush();
            $importer = self::getContainer()->get(ImportEmployeesFromXLSX::class);
            $importer->setFilePath($path);
            self::assertSame([], $importer->validateBeforeImport());
            $importer->run($import, $user);
            self::assertSame(ImportStatusEnum::DONE, $import->getStatus());
            $connection = $manager->getConnection();
            self::assertNull($connection->fetchOne('SELECT employment_from FROM employee WHERE pesel = ?', ['11111111111']));
            self::assertNull($connection->fetchOne('SELECT contract_type_uuid FROM employee WHERE pesel = ?', ['11111111111']));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM user u JOIN employee e ON e.uuid = u.employee_uuid WHERE e.pesel = ?', ['11111111111']));
            self::assertSame(1, (int) $connection->fetchOne('SELECT is_pesel_fake FROM employee WHERE pesel = ?', ['11111111111']));
            $row[0] = 'Maria';
            array_pop($row);
            foreach (['2026-10-24', null] as $employmentFrom) {
                $row[EmployeeImportColumnEnum::EMPLOYMENT_FROM->value] = $employmentFrom;
                $row[EmployeeImportColumnEnum::EMAIL->value] = 'anna@example.test';
                $row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value] = null !== $employmentFrom ? $details['contractTypeUUID'] : null;
                $updatedPath = $this->spreadsheet([$row]);
                try {
                    $importer->setFilePath($updatedPath);
                    self::assertSame([], $importer->validateBeforeImport());
                    $importer->run($import, $user);
                    self::assertSame('Maria', $connection->fetchOne('SELECT first_name FROM employee WHERE pesel = ?', ['11111111111']));
                    self::assertSame($employmentFrom, $connection->fetchOne('SELECT employment_from FROM employee WHERE pesel = ?', ['11111111111']));
                    self::assertSame($row[EmployeeImportColumnEnum::CONTACT_TYPE_UUID->value], $connection->fetchOne('SELECT contract_type_uuid FROM employee WHERE pesel = ?', ['11111111111']));
                    self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM employee WHERE pesel = ?', ['11111111111']));
                    self::assertSame(1, (int) $connection->fetchOne('SELECT is_pesel_fake FROM employee WHERE pesel = ?', ['11111111111']));
                } finally {
                    unlink($updatedPath);
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testXlsxRejectsMissingInvalidAndDuplicateLoginPhonesBeforeImport(): void
    {
        $details = $this->details();
        $row = ['Anna', null, '11111111111', 'anna@example.test', '123456789', null, null, null, null, null, $details['departmentUUID'], $details['positionUUID'], null, $details['roleUUID'], null, null, null, null, 1, 1];
        $importer = self::getContainer()->get(ImportEmployeesFromXLSX::class);
        foreach ([null, 'abc', '123'] as $phone) {
            $invalid = $row;
            $invalid[EmployeeImportColumnEnum::PHONE->value] = $phone;
            $path = $this->spreadsheet([$invalid]);
            try {
                $importer->setFilePath($path);
                self::assertNotEmpty($importer->validateBeforeImport());
            } finally {
                unlink($path);
            }
        }
        $duplicate = $row;
        $duplicate[EmployeeImportColumnEnum::PESEL->value] = '22222222222';
        $duplicate[EmployeeImportColumnEnum::PHONE->value] = '+48 123-456-789';
        $path = $this->spreadsheet([$row, $duplicate]);
        try {
            $importer->setFilePath($path);
            self::assertStringContainsString('Ten sam telefon', implode(' ', $importer->validateBeforeImport()));
            self::assertSame(0, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM employee'));
        } finally {
            unlink($path);
        }
    }

    public function testXlsxRequiresValidAndUniqueEmailAddresses(): void
    {
        $details = $this->details();
        $row = ['Anna', null, '11111111111', 'anna@example.test', '123456789', null, null, null, null, null, $details['departmentUUID'], $details['positionUUID'], null, $details['roleUUID'], null, null, null, null, 1, 1];
        $importer = self::getContainer()->get(ImportEmployeesFromXLSX::class);
        foreach ([null, '', 'invalid'] as $email) {
            $invalid = $row;
            $invalid[EmployeeImportColumnEnum::EMAIL->value] = $email;
            $path = $this->spreadsheet([$invalid]);
            try {
                $importer->setFilePath($path);
                self::assertNotEmpty($importer->validateBeforeImport());
            } finally { unlink($path); }
        }
        $duplicate = $row;
        $duplicate[EmployeeImportColumnEnum::PESEL->value] = '22222222222';
        $duplicate[EmployeeImportColumnEnum::PHONE->value] = '987654321';
        $duplicate[EmployeeImportColumnEnum::EMAIL->value] = 'ANNA@example.test';
        $path = $this->spreadsheet([$row, $duplicate]);
        try {
            $importer->setFilePath($path);
            self::assertStringContainsString('Ten sam e-mail', implode(' ', $importer->validateBeforeImport()));
        } finally { unlink($path); }
    }
}
