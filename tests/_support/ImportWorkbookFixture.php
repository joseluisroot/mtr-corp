<?php
declare(strict_types=1);
namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Synthetic application input; never uses or includes business records. */
final class ImportWorkbookFixture
{
    public static function create(array $rows,string $sheetName='Lotes'): string
    {
        $book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle($sheetName);
        $sheet->fromArray(['ID propiedad','Polígono','Número de lote','ID cliente','Estado actual'],null,'A7');
        foreach($rows as $i=>$row) $sheet->fromArray($row,null,'A'.($i+8));
        $file=WRITEPATH.'cache/import-fixture-'.bin2hex(random_bytes(8)).'.xlsx';
        $writer=new Xlsx($book);$writer->setPreCalculateFormulas(false);$writer->save($file);$book->disconnectWorksheets();
        return $file;
    }
}
