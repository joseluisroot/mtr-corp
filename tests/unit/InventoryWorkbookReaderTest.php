<?php
declare(strict_types=1);
use App\Services\InventoryWorkbookReader;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\ImportWorkbookFixture;

final class InventoryWorkbookReaderTest extends CIUnitTestCase
{
    public function testReadsIdentifiersAndDoesNotRetainClientData(): void
    {
        $file=ImportWorkbookFixture::create([
            ['=B8&"-"&C8','A','01','PERSONA-PRIVADA','Reservada'],
            ['A-02','A','02','','Disponible'],
            ['=B10&"-"&C10','','','',''],
        ]);
        try {
            $rows=(new InventoryWorkbookReader())->read($file);
            $this->assertCount(2,$rows);
            $this->assertSame('A-01',$rows[0]['source_key']);
            $this->assertSame('01',$rows[0]['lot_number']);
            $this->assertSame('ready',$rows[0]['disposition']);
            $this->assertSame(1,$rows[0]['review_required']);
            $this->assertSame('Reservado',$rows[0]['legacy_status']);
            $this->assertSame(0,$rows[1]['review_required']);
            $this->assertStringNotContainsString('PERSONA-PRIVADA',json_encode($rows));
            $this->assertStringNotContainsString('=B8',json_encode($rows));
        } finally { unlink($file); }
    }

    public function testDuplicatesAndFormulaCoordinatesCannotBeImported(): void
    {
        $file=ImportWorkbookFixture::create([
            ['X-1','A','01','','Disponible'],['X-2','A','01','','Disponible'],
            ['X-3','=1+1','03','','Disponible'],['X-4','A','04','','Desconocido'],
        ]);
        try {
            $rows=(new InventoryWorkbookReader())->read($file);
            $this->assertSame('invalid',$rows[0]['disposition']);
            $this->assertSame('invalid',$rows[1]['disposition']);
            $this->assertSame('invalid',$rows[2]['disposition']);
            $this->assertSame('Sin confirmar',$rows[3]['legacy_status']);
            $this->assertSame(1,$rows[3]['review_required']);
        } finally { unlink($file); }
    }

    public function testMissingInventorySheetIsRejected(): void
    {
        $file=ImportWorkbookFixture::create([['A-01','A','01','','Disponible']],'Otra hoja');
        try { $this->expectException(\DomainException::class);(new InventoryWorkbookReader())->read($file); }
        finally { unlink($file); }
    }

    public function testNonExcelFileIsRejected(): void
    {
        $file=tempnam(WRITEPATH.'cache','not-excel-');file_put_contents($file,'Not an Excel file');
        try { $this->expectException(\DomainException::class);(new InventoryWorkbookReader())->read($file); }
        finally { unlink($file); }
    }
}
