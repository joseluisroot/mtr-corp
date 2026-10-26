<?php
declare(strict_types=1);
use App\Services\InventoryImportService;
use App\Services\InventoryService;
use App\Services\InventoryState;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Config\Database;
use Tests\Support\ImportWorkbookFixture;

final class InventoryImportTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;

    public function testReviewSelectionIdempotencyOwnershipAndConflicts(): void
    {
        if(getenv('MTR_MYSQL_TESTS')!=='1') $this->markTestSkipped('Disposable MySQL integration not enabled.');
        $db=Database::connect('tests');$this->assertSame('MySQLi',$db->DBDriver);$this->assertStringEndsWith('_test',$db->database);
        $forge=Database::forge('tests');
        foreach(glob(ROOTPATH.'vendor/codeigniter4/shield/src/Database/Migrations/*.php') as $file) require_once $file;
        $shield=new \CodeIgniter\Shield\Database\Migrations\CreateAuthTables($forge);$shield->up();
        $settings=[];$classes=[];$files=[];
        try {
            foreach(glob(ROOTPATH.'vendor/codeigniter4/settings/src/Database/Migrations/*.php') as $file) {
                require_once $file;preg_match('/class\s+(\w+)\s+extends\s+Migration/',file_get_contents($file),$match);
                $class='CodeIgniter\\Settings\\Database\\Migrations\\'.$match[1];(new $class($forge))->up();$settings[]=$class;
            }
            foreach(glob(APPPATH.'Database/Migrations/*.php') as $file) {
                require_once $file;$class='App\\Database\\Migrations\\'.substr(basename($file,'.php'),18);(new $class($forge))->up();$classes[]=$class;
            }
            $db->table('users')->insert(['username'=>'import-admin','active'=>1]);$actor=(int)$db->insertID();
            $db->table('users')->insert(['username'=>'import-viewer','active'=>1]);$other=(int)$db->insertID();
            $inventory=new InventoryService($db);$imports=new InventoryImportService($db);
            $project=$inventory->createCatalog('projects',['code'=>'TEST','name'=>'Synthetic project'],$actor);
            $file=ImportWorkbookFixture::create([
                ['A-01','A','01','PRIVATE-CLIENT','Reservada'],['A-02','A','02','','Disponible'],['B-01','B','01','','Disponible'],
                ['X-01','X','01','','Disponible'],['X-02','X','01','','Disponible'],
            ]);$files[]=$file;
            $batch=$imports->preview($file,'synthetic.xlsx',$project,$actor);
            $review=$imports->batch($batch,$actor);$rows=$review['rows'];
            $this->assertSame(0,$db->table('lots')->countAllResults());
            $this->assertSame(0,$db->table('polygons')->countAllResults());
            $this->assertCount(5,$rows);
            $this->assertSame('invalid',$rows[3]['disposition']);
            $this->assertStringNotContainsString('PRIVATE-CLIENT',json_encode($review));
            $this->rejects(fn()=>$imports->batch($batch,$other));
            $this->rejects(fn()=>$imports->commit($batch,$other,[$rows[0]['id']],['A'=>'enabled'],true));
            $this->rejects(fn()=>$imports->commit($batch,$actor,[$rows[0]['id']],['A'=>'enabled'],false));
            $this->rejects(fn()=>$imports->commit($batch,$actor,[$rows[3]['id']],['X'=>'enabled'],true));
            $this->rejects(fn()=>$imports->commit($batch,$actor,[999999],[],true));
            $this->rejects(fn()=>$imports->commit($batch,$actor,[$rows[0]['id']],[],true));
            $this->assertSame(0,$db->table('lots')->countAllResults());
            // A later polygon error must also roll back earlier inserts in the same selection.
            $this->rejects(fn()=>$imports->commit($batch,$actor,[$rows[0]['id'],$rows[2]['id']],['A'=>'enabled'],true));
            $this->assertSame(0,$db->table('lots')->countAllResults());
            $this->assertSame(0,$db->table('polygons')->countAllResults());
            $count=$imports->commit($batch,$actor,[$rows[0]['id']],['A'=>'enabled'],true);
            $this->assertSame(1,$count);
            $this->assertSame(1,$db->table('lots')->countAllResults());
            $lot=$inventory->inventory()[0];
            $this->assertSame('review',InventoryState::effective($lot));
            $this->assertSame('01',$lot['number']);
            $this->assertSame('Reservado',$lot['legacy_status']);
            $this->assertNull($lot['area_m2']);
            $this->assertSame(0,$db->table('lot_prices')->countAllResults());
            $this->assertSame('omitted',$imports->batch($batch,$actor)['rows'][1]['disposition']);
            $this->rejects(fn()=>$imports->commit($batch,$actor,[$rows[0]['id']],['A'=>'enabled'],true));
            $second=$imports->preview($file,'same.xlsx',$project,$actor);$again=$imports->batch($second,$actor);
            $this->assertSame('existing',$again['rows'][0]['disposition']);
            $this->assertSame('ready',$again['rows'][1]['disposition']);
            // A live edit after preview invalidates the stale selection, without overwriting it.
            $polygon=(int)$lot['polygon_id'];
            $inventory->saveLot(['polygon_id'=>$polygon,'number'=>'02'],$actor);
            $this->rejects(fn()=>$imports->commit($second,$actor,[$again['rows'][1]['id']],[],true));
            $this->assertSame(2,$db->table('lots')->countAllResults());
            $this->assertSame('preview',$imports->batch($second,$actor)['status']);
            $this->assertGreaterThan(0,$db->table('inventory_audit')->where('action','import_create')->countAllResults());

            helper(['auth','setting','asset']);
            $viewer=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($other);$viewer->addGroup('viewer');$this->actingAs($viewer);
            $this->get('/importaciones')->assertStatus(403);
            $this->post('/importaciones/'.$batch.'/confirmar',[csrf_token()=>csrf_hash(),'rows'=>[$rows[0]['id']],'acknowledge'=>'1'])->assertStatus(403);
            auth()->logout();
            $admin=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($actor);$admin->addGroup('admin');$this->actingAs($admin);
            $this->get('/importaciones')->assertOK();
            $this->get('/importaciones/'.$batch)->assertSee('Resultado de la importación');
            $this->get('/importaciones/'.$second)->assertSee('Revisar inventario');
            $this->get('/inventario/'.$lot['id'])->assertSee('Propiedad importada pendiente de expediente.');
        } finally {
            if(function_exists('auth')) auth()->logout();
            foreach(array_reverse($classes) as $class) (new $class($forge))->down();
            foreach(array_reverse($settings) as $class) (new $class($forge))->down();
            $shield->down();foreach($files as $file) if(is_file($file)) unlink($file);
        }
    }
    private function rejects(callable $callback): void
    {
        try { $callback(); } catch(\DomainException $e) { $this->assertNotEmpty($e->getMessage());return; }
        $this->fail('Expected an import rule rejection.');
    }
}
