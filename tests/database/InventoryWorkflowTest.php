<?php
declare(strict_types=1);
use App\Services\InventoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Config\Database;

final class InventoryWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;

    public function testInventoryWorkflowPermissionsAndAudit(): void
    {
        if (getenv('MTR_MYSQL_TESTS')!=='1') $this->markTestSkipped('Disposable MySQL integration not enabled.');
        $db=Database::connect('tests');
        $this->assertSame('MySQLi',$db->DBDriver);
        $this->assertStringEndsWith('_test',$db->database);
        $forge=Database::forge('tests');
        foreach(glob(ROOTPATH.'vendor/codeigniter4/shield/src/Database/Migrations/*.php') as $file) require_once $file;
        $shield=new \CodeIgniter\Shield\Database\Migrations\CreateAuthTables($forge);
        $shield->up();
        $settingMigrations=[];
        foreach(glob(ROOTPATH.'vendor/codeigniter4/settings/src/Database/Migrations/*.php') as $file) {
            require_once $file;
            preg_match('/class\s+(\w+)\s+extends\s+Migration/',file_get_contents($file),$match);
            $class='CodeIgniter\\Settings\\Database\\Migrations\\'.$match[1];
            (new $class($forge))->up();$settingMigrations[]=$class;
        }
        $classes=[];
        try {
            foreach(glob(APPPATH.'Database/Migrations/*.php') as $file) {
                require_once $file;$class='App\\Database\\Migrations\\'.substr(basename($file,'.php'),18);(new $class($forge))->up();$classes[]=$class;
            }
            (new \App\Database\Seeds\InitialSettingsSeeder(config(Database::class),$db))->run();
            $db->table('users')->insert(['username'=>'synthetic-admin','active'=>1]);$actor=(int)$db->insertID();
            $db->table('users')->insert(['username'=>'synthetic-viewer','active'=>1]);$viewerId=(int)$db->insertID();
            $service=new InventoryService($db);
            $project=$service->createCatalog('projects',['code'=>'TEST','name'=>'Synthetic project'],$actor);
            $polygon=$service->createCatalog('polygons',['code'=>'A','name'=>'Polígono A','project_id'=>$project,'sales_policy'=>'partial'],$actor);
            $model=$service->createCatalog('house_models',['code'=>'B','name'=>'Synthetic house'],$actor);
            $type=$service->createCatalog('lot_types',['code'=>'T1','name'=>'Small lot','front_m'=>'10','depth_m'=>'20','area_m2'=>'200'],$actor);
            $service->createCatalog('lot_types',['code'=>'T1','name'=>'Updated lot','front_m'=>'12','depth_m'=>'20','area_m2'=>'240','is_active'=>'0'],$actor,$type);
            $this->assertSame('240.000',$db->table('lot_types')->where('id',$type)->get()->getRow()->area_m2);
            $this->assertSame('0',(string)$db->table('lot_types')->where('id',$type)->get()->getRow()->is_active);
            $lot=$service->saveLot(['polygon_id'=>$polygon,'number'=>'01','front_m'=>'10','area_m2'=>'200'],$actor);
            $this->assertSame('blocked',$service->detail($lot)['effective_status']);
            $this->expectDomain(fn()=>$service->saveLot(['polygon_id'=>$polygon,'number'=>'01'],$actor));
            $this->expectDomain(fn()=>$service->saveLot(['polygon_id'=>$polygon,'number'=>'02','front_m'=>'-1'],$actor));
            $this->expectDomain(fn()=>$service->saveLot(['polygon_id'=>$polygon,'number'=>'01','lock_version'=>3],$actor,$lot));
            $service->saveLot(['polygon_id'=>$polygon,'number'=>'01','lock_version'=>0,'is_enabled'=>'1','notes'=>'<script>unsafe</script>'],$actor,$lot);
            $this->assertSame('available',$service->detail($lot)['effective_status']);
            $this->expectDomain(fn()=>$service->saveLot(['polygon_id'=>$polygon,'number'=>'01','lock_version'=>0],$actor,$lot));
            $service->polygonPolicy($polygon,'blocked',$actor);
            $this->assertSame('blocked',$service->detail($lot)['effective_status']);
            $this->expectDomain(fn()=>$service->createCatalog('users',['code'=>'X','name'=>'No'],$actor));
            $service->compatibility($lot,$model,true,$actor);
            $compat=(int)$service->detail($lot)['models'][0]['id'];
            $service->revisePrice($lot,null,['amount'=>'123.45'],$actor);
            $this->assertLessThanOrEqual(\CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateTimeString(),$service->detail($lot)['prices'][0]['valid_from']);
            $service->revisePrice($lot,$compat,['land_amount'=>'123.45','construction_amount'=>'10.20'],$actor);
            $this->assertSame('133.65',$service->detail($lot)['models'][0]['prices'][0]['total_amount']);
            $this->expectDomain(fn()=>$service->revisePrice($lot,null,['amount'=>'-1'],$actor));
            $this->expectDomain(fn()=>$service->revisePrice($lot,999999,['land_amount'=>'1','construction_amount'=>'1'],$actor));
            $db->table('lot_prices')->where('lot_id',$lot)->update(['valid_from'=>'2026-01-01 00:00:00']);
            $service->revisePrice($lot,null,['amount'=>'200.01'],$actor);
            $prices=$service->detail($lot)['prices'];
            $this->assertCount(2,$prices);
            $this->assertSame($prices[0]['valid_from'],$prices[1]['valid_until']);
            $service->compatibility($lot,$model,false,$actor);
            $this->expectDomain(fn()=>$service->revisePrice($lot,$compat,['land_amount'=>'1','construction_amount'=>'1'],$actor));
            $service->updateSettings(['negotiation_days'=>'20','payment_grace_days'=>'12','reason'=>'Synthetic revision'],$actor);
            $this->assertSame(4,$db->table('business_setting_history')->countAllResults());
            $this->expectDomain(fn()=>$service->updateSettings(['negotiation_days'=>'0','payment_grace_days'=>'10','reason'=>'Synthetic'],$actor));
            $this->assertGreaterThan(5,$db->table('inventory_audit')->countAllResults());
            // A failed actor FK must roll back the business write as well as its audit.
            try { $service->createCatalog('house_models',['code'=>'ROLLBACK','name'=>'Rollback'],$actor+999999); } catch (\Throwable) {}
            $this->assertSame(0,$db->table('house_models')->where('code','ROLLBACK')->countAllResults());
            // The following HTTP calls model new requests after the deliberate SQL failure.
            $db->resetTransStatus();
            $this->assertCount(0,$service->inventory(['status'=>'available']));
            $this->assertCount(1,$service->inventory(['q'=>'01','status'=>'blocked']));

            helper(['auth','setting']);
            $viewer=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($viewerId);$viewer->addGroup('viewer');
            $this->actingAs($viewer);
            $this->get('/inventario')->assertOK();
            $this->get('/catalogos')->assertStatus(403);
            $this->get('/configuracion')->assertStatus(403);
            $this->get('/inventario/nuevo')->assertStatus(403);
            $this->get('/inventario/'.$lot)->assertSee('&lt;script&gt;unsafe&lt;/script&gt;');
            $this->post('/inventario', [csrf_token()=>csrf_hash(),'polygon_id'=>$polygon,'number'=>'999'])->assertStatus(403);
            $this->assertSame(0,$db->table('lots')->where('number','999')->countAllResults());
            auth()->logout();
            $admin=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($actor);$admin->addGroup('admin');
            $this->actingAs($admin);
            $this->get('/catalogos')->assertOK();
            $this->get('/configuracion')->assertOK();
            $this->get('/inventario/nuevo')->assertOK();
            $this->get('/inventario/'.$lot.'/editar')->assertOK();
            $this->get('/catalogos/lot_types/'.$type)->assertOK();
            $this->get('/inventario/'.$lot)->assertSee('Administrar compatibilidad');
            $this->post('/inventario',[csrf_token()=>csrf_hash(),'polygon_id'=>$polygon,'number'=>'02'])->assertRedirect();
            $this->assertSame(1,$db->table('lots')->where('number','02')->countAllResults(),(string)session('error'));
            auth()->logout();
            $this->get('/inventario')->assertRedirectTo('/login');
            $this->get('/login')->assertOK();
            $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
            $this->get('/register');
        } finally {
            foreach(array_reverse($classes) as $class) (new $class($forge))->down();
            foreach(array_reverse($settingMigrations) as $class) (new $class($forge))->down();
            $shield->down();
        }
    }
    private function expectDomain(callable $callback): void
    {
        try { $callback(); } catch (\DomainException $e) { $this->assertNotEmpty($e->getMessage());return; }
        $this->fail('Expected a business rule rejection.');
    }
}
