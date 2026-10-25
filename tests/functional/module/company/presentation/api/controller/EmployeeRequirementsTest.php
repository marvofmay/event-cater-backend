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

    public function testRequiredContactsAndPeselCannotBeEmpty(): void
    {
        $details = $this->details();
        foreach ([['phones' => null], ['phones' => []], ['phones' => ['   ']], ['email' => ''], ['email' => 'invalid'], ['pesel' => '']] as $changes) {
            $this->client->jsonRequest('POST', '/api/employees', array_replace($details, $changes));
            self::assertResponseStatusCodeSame(422, $this->client->getResponse()->getContent());
        }
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
        $row = ['Anna', null, '11111111111', 'anna@example.test', '123456789', null, null, null, null, '2026-10-24', $details['departmentUUID'], $details['positionUUID'], $details['contractTypeUUID'], $details['roleUUID'], null, null, null, null, 1, 1];
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
            self::assertSame(1, (int) $connection->fetchOne('SELECT is_pesel_fake FROM employee WHERE pesel = ?', ['11111111111']));
            $row[0] = 'Maria';
            array_pop($row);
            $updatedPath = $this->spreadsheet([$row]);
            try {
                $importer->setFilePath($updatedPath);
                self::assertSame([], $importer->validateBeforeImport());
                $importer->run($import, $user);
                self::assertSame('Maria', $connection->fetchOne('SELECT first_name FROM employee WHERE pesel = ?', ['11111111111']));
                self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM employee WHERE pesel = ?', ['11111111111']));
                self::assertSame(1, (int) $connection->fetchOne('SELECT is_pesel_fake FROM employee WHERE pesel = ?', ['11111111111']));
            } finally {
                unlink($updatedPath);
            }
        } finally {
            unlink($path);
        }
    }
}
