<?php

declare(strict_types=1);

namespace App\tests\functional\module\company\presentation\api\controller\department;

use App\Module\Company\Application\Validator\Department\Import\EmailValidator;
use App\Module\Company\Domain\Entity\Company;
use App\Module\Company\Domain\Entity\Industry;
use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Enum\DepartmentImportColumnEnum;
use App\Module\Company\Domain\Service\Department\ImportDepartmentsFromXLSX;
use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Entity\Import;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use App\tests\functional\FunctionalTestBase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ramsey\Uuid\Uuid;

final class OptionalDepartmentContactsTest extends FunctionalTestBase
{
    private function company(): string
    {
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
        return $company->getUUID()->toString();
    }

    private function details(string $companyUuid, string $code): array
    {
        return [
            'name' => 'Departament '.$code, 'internalCode' => $code, 'companyUUID' => $companyUuid,
            'address' => ['street' => 'Testowa 1', 'postcode' => '00-001', 'city' => 'Warszawa', 'country' => 'Polska'],
        ];
    }

    private function api(string $method, string $path, array $body, int $status): void
    {
        self::assertStringContainsString('_test', $this->em->getConnection()->getDatabase());
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
    }

    private function uuid(string $code): string
    {
        return (string) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT uuid FROM department WHERE internal_code = ?', [$code]);
    }

    private function contactCount(string $uuid): int
    {
        return (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM contact WHERE department_uuid = ? AND deleted_at IS NULL', [$uuid]);
    }

    public function testCreateAndUpdateWithoutPhonesOrEmails(): void
    {
        $this->getAuthenticatedClient();
        $companyUuid = $this->company();
        foreach ([[], ['phones' => [], 'emails' => []], ['phones' => null, 'emails' => null], ['phones' => [''], 'emails' => ['']]] as $index => $contacts) {
            $code = 'EMPTY-'.$index;
            $details = $this->details($companyUuid, $code);
            $this->api('POST', '/api/departments', $details + $contacts, 201);
            $uuid = $this->uuid($code);
            self::assertNotEmpty($uuid);
            self::assertSame(0, $this->contactCount($uuid));
            $this->api('PUT', '/api/departments/'.$uuid, $details + ['phones' => ['123456789'], 'emails' => ['biuro@example.test']], 200);
            self::assertSame(2, $this->contactCount($uuid));
            $this->api('PUT', '/api/departments/'.$uuid, $details + $contacts, 200);
            self::assertSame(0, $this->contactCount($uuid));
        }
        $this->api('POST', '/api/departments', $this->details($companyUuid, 'TOO-MANY') + ['phones' => ['1', '2', '3', '4']], 422);
    }

    public function testRealXlsxImportCreatesAndUpdatesDepartmentsWithEmptyContacts(): void
    {
        $this->getAuthenticatedClient();
        $companyUuid = $this->company();
        $this->api('POST', '/api/departments', $this->details($companyUuid, 'EXISTING') + ['phones' => ['123456789'], 'emails' => ['biuro@example.test']], 201);
        $path = tempnam(sys_get_temp_dir(), 'department-contacts-');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nazwa', 'Kod', 'Ulica', 'Kod pocztowy', 'Miasto', 'Kraj', 'Telefon', 'Firma UUID', 'Opis', 'Nadrzędny kod', 'E-mail', 'WWW', 'Aktywny'], null, 'A1');
        $sheet->fromArray([
            ['Nowy bez kontaktów', 'IMPORTED', 'Testowa 1', '00-001', 'Warszawa', 'Polska', null, $companyUuid, 'Przykładowy opis departamentu do testu importu.', null, null, null, true],
            ['Zaktualizowany bez kontaktów', 'EXISTING', 'Testowa 1', '00-001', 'Warszawa', 'Polska', '   ', $companyUuid, 'Przykładowy opis departamentu do testu importu.', null, '   ', null, true],
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        try {
            $manager = self::getContainer()->get('doctrine')->getManager();
            $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com']);
            $file = File::create('departments.xlsx', $path, 'xlsx', 'import', $user);
            $import = Import::create(ImportKindEnum::IMPORT_DEPARTMENTS, ImportStatusEnum::PENDING, $file, $user);
            $manager->persist($file);
            $manager->persist($import);
            $manager->flush();
            $importer = self::getContainer()->get(ImportDepartmentsFromXLSX::class);
            $importer->setFilePath($path);
            self::assertSame([], $importer->validateBeforeImport());
            $importer->run($import, $user);
            self::assertSame(ImportStatusEnum::DONE, $import->getStatus());
            self::assertNotEmpty($this->uuid('IMPORTED'));
            self::assertSame(0, $this->contactCount($this->uuid('IMPORTED')));
            self::assertSame(0, $this->contactCount($this->uuid('EXISTING')));
        } finally {
            unlink($path);
        }
    }

    public function testImportAllowsEmptyEmailsButStillRejectsInvalidAndDuplicateValues(): void
    {
        $validator = self::getContainer()->get(EmailValidator::class);
        foreach ([null, '', '   '] as $email) {
            self::assertNull($validator->validate([DepartmentImportColumnEnum::EMAIL->value => $email]));
        }
        self::assertNull($validator->validate([]));
        self::assertNotNull($validator->validate([DepartmentImportColumnEnum::EMAIL->value => 'invalid']));
        $row = [DepartmentImportColumnEnum::EMAIL->value => 'biuro@example.test', DepartmentImportColumnEnum::DEPARTMENT_INTERNAL_CODE->value => 'DEP-1'];
        self::assertNull($validator->validate($row));
        self::assertNotNull($validator->validate($row, ['emailsInternalCodes' => ['biuro@example.test' => 'DEP-2']]));
        self::assertNull($validator->validate($row, ['emailsInternalCodes' => ['biuro@example.test' => 'DEP-1']]));
    }
}
