<?php
declare(strict_types=1);
use App\Services\ClientService;
use App\Services\InventoryService;
use App\Services\InventoryState;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Config\Database;

final class ClientWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;
    public function testClientsDocumentsPropertyLinksAndPermissions(): void
    {
        if(getenv('MTR_MYSQL_TESTS')!=='1') $this->markTestSkipped('Disposable MySQL integration not enabled.');
        $db=Database::connect('tests');$this->assertSame('MySQLi',$db->DBDriver);$this->assertStringEndsWith('_test',$db->database);$forge=Database::forge('tests');
        foreach(glob(ROOTPATH.'vendor/codeigniter4/shield/src/Database/Migrations/*.php') as $file) require_once $file;
        $shield=new \CodeIgniter\Shield\Database\Migrations\CreateAuthTables($forge);$shield->up();$settings=[];$classes=[];
        try {
            foreach(glob(ROOTPATH.'vendor/codeigniter4/settings/src/Database/Migrations/*.php') as $file) {
                require_once $file;preg_match('/class\s+(\w+)\s+extends\s+Migration/',file_get_contents($file),$m);$class='CodeIgniter\\Settings\\Database\\Migrations\\'.$m[1];(new $class($forge))->up();$settings[]=$class;
            }
            foreach(glob(APPPATH.'Database/Migrations/*.php') as $file) {require_once $file;$class='App\\Database\\Migrations\\'.substr(basename($file,'.php'),18);(new $class($forge))->up();$classes[]=$class;}
            $db->table('users')->insert(['username'=>'client-admin-test','active'=>1]);$actor=(int)$db->insertID();
            $db->table('users')->insert(['username'=>'client-viewer-test','active'=>1]);$viewerId=(int)$db->insertID();
            $clients=new ClientService($db);$inventory=new InventoryService($db);
            $payload=['full_name'=>'Cliente sintético Uno','code'=>'CLI-UNO','document_type'=>'passport','document_country'=>'sv','document_number'=>'AB-123','email'=>'synthetic@example.test'];
            $client=$clients->save($payload,$actor);
            $second=$clients->save(['full_name'=>'Cliente sintético Uno','code'=>'CLI-DOS'],$actor);
            $this->assertSame('SV:passport:AB123',$clients->detail($client)['document_key']);
            $this->assertSame(2,$clients->search('Cliente sintético')['total']);
            $this->assertSame(1,$clients->search('CLI-UNO')['total']);
            $this->rejects(fn()=>$clients->save($payload+['phone'=>'1'],$actor));
            $duplicate=$payload;$duplicate['code']='CLI-TRES';$duplicate['document_number']='ab 123';
            $this->rejects(fn()=>$clients->save($duplicate,$actor));
            $this->rejects(fn()=>$clients->save(['full_name'=>'Test client','document_number'=>'123'],$actor));
            $this->rejects(fn()=>$clients->save(['full_name'=>'Test client','email'=>'invalid'],$actor));
            $this->rejects(fn()=>$clients->save($payload+['lock_version'=>9],$actor,$client));
            $clients->save($payload+['lock_version'=>0,'phone'=>'12345678'],$actor,$client);
            $this->assertSame('12345678',$clients->detail($client)['phone']);
            $this->rejects(fn()=>$clients->save($payload+['lock_version'=>0],$actor,$client));
            $project=$inventory->createCatalog('projects',['code'=>'TEST','name'=>'Synthetic project'],$actor);
            $polygon=$inventory->createCatalog('polygons',['code'=>'A','name'=>'Polígono A','project_id'=>$project,'sales_policy'=>'enabled'],$actor);
            $lot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'01'],$actor);
            $otherLot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'02'],$actor);
            $db->table('lots')->where('id',$lot)->update(['import_review_required'=>1,'legacy_status'=>'Reservado']);
            $clients->link($lot,$client,'Antecedente ficticio de expediente',$actor);
            $clients->link($otherLot,$client,'Segundo antecedente ficticio',$actor);
            $this->assertCount(2,$clients->detail($client)['properties']);
            $this->assertSame('review',InventoryState::effective($inventory->detail($lot)));
            $this->assertSame('available',InventoryState::effective($inventory->detail($otherLot)));
            $this->assertSame(0,$db->table('lot_prices')->countAllResults());
            $this->rejects(fn()=>$clients->link($lot,$second,'Intento de sobrescribir',$actor));
            $link=$clients->propertyFile($lot);
            $this->rejects(fn()=>$clients->unlink($lot,(int)$link['id']+999,'Vínculo no coincidente',$actor));
            $this->rejects(fn()=>$clients->unlink($lot,(int)$link['id'],'',$actor));
            $clients->unlink($lot,(int)$link['id'],'Corrección de antecedente sintético',$actor);
            $this->assertNull($clients->propertyFile($lot));
            $this->assertCount(2,$clients->detail($client)['properties']);
            $clients->link($lot,$second,'Nuevo expediente sintético',$actor);
            $this->assertSame($second,(int)$clients->propertyFile($lot)['client_id']);
            $clients->save(['full_name'=>'Cliente sintético Uno','code'=>'CLI-DOS','is_active'=>'0','lock_version'=>0],$actor,$second);
            $this->assertSame(1,$clients->search('',1,true)['total']);
            $thirdLot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'03'],$actor);
            $this->rejects(fn()=>$clients->link($thirdLot,$second,'Cliente inactivo ficticio',$actor));
            $this->assertSame(1,$db->table('client_property_links')->where('lot_id',$lot)->where('is_active',1)->countAllResults());
            $this->assertStringNotContainsString('AB123',json_encode($db->table('inventory_audit')->get()->getResultArray()));

            helper(['auth','setting','asset']);
            $viewer=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($viewerId);$viewer->addGroup('viewer');$this->actingAs($viewer);
            $this->get('/clientes')->assertStatus(403);
            $this->get('/inventario/'.$lot)->assertDontSee('Cliente sintético Uno');
            $this->get('/inventario/'.$lot.'/cliente')->assertStatus(403);
            $this->post('/clientes',[csrf_token()=>csrf_hash(),'full_name'=>'Unauthorized'])->assertStatus(403);
            auth()->logout();
            $admin=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($actor);$admin->addGroup('admin');$this->actingAs($admin);
            $this->get('/clientes')->assertOK();$this->get('/clientes/nuevo')->assertOK();
            $this->get('/clientes/'.$client)->assertSee('Propiedades vinculadas al expediente');
            $this->get('/clientes/'.$client.'/editar')->assertOK();
            $this->get('/inventario/'.$thirdLot.'/cliente')->assertSee('Vincular cliente');
            $this->get('/inventario/'.$lot)->assertSee('Cliente del expediente');
            $this->post('/clientes',[csrf_token()=>csrf_hash(),'full_name'=>'Cliente desde formulario'])->assertRedirect();
            $this->assertSame(3,$db->table('clients')->countAllResults());
        } finally {
            if(function_exists('auth')) auth()->logout();
            foreach(array_reverse($classes) as $class) (new $class($forge))->down();
            foreach(array_reverse($settings) as $class) (new $class($forge))->down();$shield->down();
        }
    }
    private function rejects(callable $callback): void
    {
        try {$callback();} catch(\DomainException $e) {$this->assertNotEmpty($e->getMessage());return;}
        $this->fail('Expected a client rule rejection.');
    }
}
