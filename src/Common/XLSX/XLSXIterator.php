<?php

declare(strict_types=1);

namespace App\Common\XLSX;

use App\Common\Domain\Interface\XLSXIteratorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class XLSXIterator implements XLSXIteratorInterface
{
    protected ?Worksheet $worksheet = null;
    protected array $errors = [];
    protected int $rowIndex = 2;
    private string $filePath = '';
    private ?Spreadsheet $spreadsheet = null;
    private ?array $rows = null;

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function setFilePath(string $filePath): void
    {
        if ($this->filePath === $filePath) {
            return;
        }

        $this->releaseSpreadsheet();
        $this->filePath = $filePath;
        $this->errors = [];
        $this->rowIndex = 2;
        $this->rows = null;
    }

    public function loadFile(): void
    {
        if (null !== $this->worksheet) {
            return;
        }

        if (!file_exists($this->filePath)) {
            throw new \RuntimeException(sprintf('%s: %s', $this->translator->trans('import.fileNotExists', [], 'validators'), $this->filePath));
        }

        $reader = IOFactory::createReaderForFile($this->filePath);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $this->spreadsheet = $reader->load($this->filePath);
        $this->worksheet = $this->spreadsheet->getActiveSheet();

        if ($this->worksheet->getHighestDataRow() < 2) {
            throw new \RuntimeException($this->translator->trans('import.noData', [], 'validators'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function validateBeforeImport(): array
    {
        $this->errors = [];
        $this->rowIndex = 2;

        foreach ($this->import() as $rowData) {
            if ($error = $this->validateRow($rowData, $this->rowIndex)) {
                array_push($this->errors, ...$error);
            }

            ++$this->rowIndex;
        }

        return $this->errors;
    }

    public function iterateRows(): array
    {
        if (null !== $this->rows) {
            return $this->rows;
        }

        if (!$this->worksheet) {
            throw new \RuntimeException($this->translator->trans('import.chooseFile', [], 'validators'));
        }

        $range = sprintf('A2:%s%d', $this->worksheet->getHighestDataColumn(), $this->worksheet->getHighestDataRow());
        $this->rows = $this->worksheet->rangeToArray($range, null, true, false, false);

        return $this->rows;
    }

    public function import(): array
    {
        $this->loadFile();

        return $this->iterateRows();
    }

    abstract public function validateRow(array $row, int $index): array;

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function __destruct()
    {
        $this->releaseSpreadsheet();
    }

    private function releaseSpreadsheet(): void
    {
        $this->spreadsheet?->disconnectWorksheets();
        $this->spreadsheet = null;
        $this->worksheet = null;
    }
}
