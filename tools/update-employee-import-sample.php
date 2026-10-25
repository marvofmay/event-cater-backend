<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__).'/vendor/autoload.php';

if (3 !== $argc) {
    throw new InvalidArgumentException('Usage: php tools/update-employee-import-sample.php input.xlsx output.xlsx');
}

$spreadsheet = IOFactory::load($argv[1]);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('T1', 'isPeselFake');
$sheet->getColumnDimension('T')->setAutoSize(true);
$sheet->getComment('T1')->getText()->createTextRun('1 = fikcyjny PESEL, 0 = prawdziwy. Puste pole zachowuje dotychczasowe oznaczenie pracownika. PESEL, telefon i e-mail są wymagane; nazwisko i adres są opcjonalne.');
for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
    $sheet->setCellValue('T'.$row, 1);
}

(new Xlsx($spreadsheet))->save($argv[2]);
$spreadsheet->disconnectWorksheets();
