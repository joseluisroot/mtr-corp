<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/** Reads only the inventory identifiers. No formulas are evaluated or personal data retained. */
final class InventoryWorkbookReader
{
    public const MAX_ROWS = 2000;

    public function read(string $path): array
    {
        $this->checkArchive($path);
        $reader = new Xlsx();
        if (!$reader->canRead($path)) throw new DomainException('El archivo no es un Excel .xlsx válido.');
        $info = $reader->listWorksheetInfo($path);
        $sheetInfo = array_values(array_filter($info,fn($s)=>$s['worksheetName']==='Lotes'));
        if (!$sheetInfo) throw new DomainException('Falta la hoja Lotes. Usa el formato de Control Administrativo.');
        if ($sheetInfo[0]['totalRows'] > self::MAX_ROWS + 20) throw new DomainException('La hoja supera las 2,000 filas admitidas. Divide el archivo.');
        $reader->setLoadSheetsOnly('Lotes');
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row<=2020 && strlen($columnAddress)===1 && $columnAddress<='M';
            }
        });
        try {
            $book = $reader->load($path);
            $sheet = $book->getSheetByName('Lotes');
            $headerRow = null;
            for($row=1;$row<=20;$row++) {
                $a=$this->normalize((string)$sheet->getCell('A'.$row)->getValue());
                $b=$this->normalize((string)$sheet->getCell('B'.$row)->getValue());
                $c=$this->normalize((string)$sheet->getCell('C'.$row)->getValue());
                if($a==='idpropiedad' && $b==='poligono' && in_array($c,['numerodelote','lote'],true)) { $headerRow=$row;break; }
            }
            if(!$headerRow) throw new DomainException('No se reconocieron los encabezados A: ID propiedad, B: Polígono, C: Número de lote.');
            // Detect source commitments by their column labels, not by cached calculated values.
            $clientColumn=null;$statusColumn=null;$conditionColumn=null;
            foreach(range('A','M') as $col) {
                $name=$this->normalize((string)$sheet->getCell($col.$headerRow)->getValue());
                if($name==='idcliente') $clientColumn=$col;
                if($name==='condiciondellote') $conditionColumn=$col;
                if(in_array($name,['estadoactual','estado'],true)) $statusColumn=$col;
            }
            $rows=[];
            for($r=$headerRow+1;$r<=$sheetInfo[0]['totalRows'];$r++) {
                $values=[];$formula=false;
                foreach(['key'=>'A','polygon'=>'B','number'=>'C'] as $field=>$col) {
                    $cell=$sheet->getCell($col.$r);
                    if($cell->isFormula() && $field!=='key') $formula=true;
                    $value=$cell->getValue();
                    if($cell->isFormula() && $field==='key') $value='';
                    $values[$field]=is_scalar($value)?trim((string)$value):'';
                    // Retain explicit zero-padding of numeric identifiers without evaluating formulas.
                    if(!$cell->isFormula() && is_numeric($value) && preg_match('/^0{2,}$/',$cell->getStyle()->getNumberFormat()->getFormatCode())) $values[$field]=$cell->getFormattedValue();
                }
                if(implode('',$values)==='' && !$formula) continue;
                if($values['key']==='' && $values['polygon']!=='' && $values['number']!=='') $values['key']=$values['polygon'].'-'.$values['number'];
                $issues=[];
                if($formula) $issues[]='Identificador con fórmula: reemplazar por un valor verificado.';
                foreach(['polygon'=>30,'number'=>30,'key'=>80] as $field=>$limit) {
                    $values[$field]=strtoupper($values[$field]);
                    if($values[$field]==='' || mb_strlen($values[$field])>$limit || !preg_match('/^[A-Z0-9-]+$/',$values[$field])) $issues[]='Identificador inválido: '.$field.'.';
                }
                $statusCell=$statusColumn?$sheet->getCell($statusColumn.$r):null;
                $rawStatus=$statusCell?($statusCell->isFormula()?$statusCell->getOldCalculatedValue():$statusCell->getValue()):'';
                $status=$this->status(is_scalar($rawStatus)?(string)$rawStatus:'');
                $hasClient=$clientColumn && trim((string)$sheet->getCell($clientColumn.$r)->getValue())!=='';
                $condition=$conditionColumn?$this->normalize((string)$sheet->getCell($conditionColumn.$r)->getValue()):'';
                $restricted=$condition!=='' && !in_array($condition,['disponible','habilitado','libre'],true);
                if($status==='Disponible' && $restricted) $status='Disponible (con restricción)';
                $review=$status!=='Disponible' || $hasClient || $restricted;
                $rows[]=['source_row'=>$r,'source_key'=>$values['key'],'polygon_code'=>$values['polygon'],'lot_number'=>$values['number'],'legacy_status'=>$status,'review_required'=>(int)$review,'issues'=>$issues,'disposition'=>$issues?'invalid':'ready'];
            }
            if(!$rows) throw new DomainException('No hay lotes con identificadores en la hoja Lotes.');
            if(count($rows)>self::MAX_ROWS) throw new DomainException('Se admiten hasta 2,000 lotes por archivo.');
            $keys=[];$codes=[];
            foreach($rows as $i=>$row) { $keys[$row['polygon_code'].'/'.$row['lot_number']][]=$i;$codes[$row['source_key']][]=$i; }
            foreach(array_merge(array_values($keys),array_values($codes)) as $indexes) if(count($indexes)>1) foreach($indexes as $i) {
                $rows[$i]['disposition']='invalid';$rows[$i]['issues'][]='Identificador repetido dentro del archivo.';
            }
            return $rows;
        } finally {
            if(isset($book)) $book->disconnectWorksheets();
        }
    }

    private function status(string $value): string
    {
        return match($this->normalize($value)) {
            'disponible'=>'Disponible',
            'reservada','reservado'=>'Reservado',
            'porconfirmar','ennegociacion'=>'En negociación',
            'pagada','pagado'=>'Pagado (origen)',
            'vendida','vendido'=>'Vendido (origen)',
            'bloqueada','bloqueado'=>'Bloqueado (origen)',
            default=>'Sin confirmar',
        };
    }
    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/','',strtolower(iconv('UTF-8','ASCII//TRANSLIT',$value)));
    }
    private function checkArchive(string $path): void
    {
        if(!is_file($path) || filesize($path)>5*1024*1024) throw new DomainException('El archivo debe pesar como máximo 5 MB.');
        $zip=new \ZipArchive();
        if($zip->open($path)!==true) throw new DomainException('No se pudo abrir el Excel.');
        try {
            if($zip->numFiles>1500) throw new DomainException('El archivo contiene demasiados componentes.');
            $total=0;
            for($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);$total+=$stat['size'];
                if($total>30*1024*1024) throw new DomainException('El contenido descomprimido supera 30 MB.');
                if(str_contains(strtolower($stat['name']),'vbaproject')) throw new DomainException('No se admiten archivos con macros.');
            }
        } finally { $zip->close(); }
    }
}
