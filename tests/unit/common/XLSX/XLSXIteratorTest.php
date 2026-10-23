<?php

declare(strict_types=1);

namespace App\tests\unit\common\XLSX;

use App\Common\XLSX\XLSXIterator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class XLSXIteratorTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = tempnam(sys_get_temp_dir(), 'xlsx-iterator-');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['name', 'description'],
            ['Admin', 'Administrators'],
            ['User', ''],
        ]);
        (new Xlsx($spreadsheet))->save($this->filePath);
        $spreadsheet->disconnectWorksheets();
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);
    }

    public function testItCachesRowsUsedDuringValidationAndDoesNotDuplicateErrors(): void
    {
        $iterator = $this->createIterator();
        $iterator->setFilePath($this->filePath);

        self::assertSame(['missing description at row 3'], $iterator->validateBeforeImport());
        self::assertSame(['missing description at row 3'], $iterator->validateBeforeImport());
        self::assertSame([
            ['Admin', 'Administrators'],
            ['User', null],
        ], $iterator->import());
    }

    public function testChangingFilePathClearsPreviouslyImportedRows(): void
    {
        $secondFilePath = tempnam(sys_get_temp_dir(), 'xlsx-iterator-');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['name', 'description'],
            ['Manager', 'Managers'],
        ]);
        (new Xlsx($spreadsheet))->save($secondFilePath);
        $spreadsheet->disconnectWorksheets();

        try {
            $iterator = $this->createIterator();
            $iterator->setFilePath($this->filePath);
            self::assertCount(2, $iterator->import());

            $iterator->setFilePath($secondFilePath);
            self::assertSame([['Manager', 'Managers']], $iterator->import());
        } finally {
            @unlink($secondFilePath);
        }
    }

    private function createIterator(): XLSXIterator
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return new class($translator) extends XLSXIterator {
            public function validateRow(array $row, int $index): array
            {
                // Existing importers call import() from validation; this must reuse cached rows.
                $this->import();

                return empty($row[1]) ? [sprintf('missing description at row %d', $index)] : [];
            }
        };
    }
}
