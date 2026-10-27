<?php
declare(strict_types=1);
use App\Services\CommercialService;
use App\Services\CommercialDocuments;
use App\Services\InventoryService;
use App\Services\ClientService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Config\Database;

final class CommercialWorkflowTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;
    public function testExclusiveLifecycleHistoricalReviewDocumentsAndPermissions(): void
    {
        if(getenv('MTR_MYSQL_TESTS')!=='1') $this->markTestSkipped('Disposable MySQL integration not enabled.');
        $db=Database::connect('tests');$this->assertSame('MySQLi',$db->DBDriver);$this->assertStringEndsWith('_test',$db->database);$forge=Database::forge('tests');
        foreach(glob(ROOTPATH.'vendor/codeigniter4/shield/src/Database/Migrations/*.php') as $file) require_once $file;
        $shield=new \CodeIgniter\Shield\Database\Migrations\CreateAuthTables($forge);$shield->up();$settings=[];$classes=[];$documents=[];
        try {
            foreach(glob(ROOTPATH.'vendor/codeigniter4/settings/src/Database/Migrations/*.php') as $file) {
                require_once $file;preg_match('/class\s+(\w+)\s+extends\s+Migration/',file_get_contents($file),$m);$class='CodeIgniter\\Settings\\Database\\Migrations\\'.$m[1];(new $class($forge))->up();$settings[]=$class;
            }
            foreach(glob(APPPATH.'Database/Migrations/*.php') as $file) {require_once $file;$class='App\\Database\\Migrations\\'.substr(basename($file,'.php'),18);(new $class($forge))->up();$classes[]=$class;}
            (new \App\Database\Seeds\InitialSettingsSeeder(config(Database::class),$db))->run();
            $db->table('users')->insert(['username'=>'commercial-admin-test','active'=>1]);$actor=(int)$db->insertID();
            $db->table('users')->insert(['username'=>'commercial-viewer-test','active'=>1]);$viewerId=(int)$db->insertID();
            $inventory=new InventoryService($db);$clients=new ClientService($db);$service=new CommercialService($db);
            $project=$inventory->createCatalog('projects',['code'=>'TEST','name'=>'Synthetic project'],$actor);
            $polygon=$inventory->createCatalog('polygons',['code'=>'D','name'=>'Polígono D','project_id'=>$project,'sales_policy'=>'enabled'],$actor);
            $blocked=$inventory->createCatalog('polygons',['code'=>'C','name'=>'Polígono C','project_id'=>$project,'sales_policy'=>'blocked'],$actor);
            $lot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'01'],$actor);
            $historicalLot=$inventory->saveLot(['polygon_id'=>$blocked,'number'=>'01'],$actor);
            $missingPriceLot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'02'],$actor);
            $db->table('lots')->where('id',$historicalLot)->update(['import_review_required'=>1,'legacy_status'=>'Reservado']);
            $first=$clients->save(['full_name'=>'Cliente comercial Uno'],$actor);$second=$clients->save(['full_name'=>'Cliente comercial Dos'],$actor);
            $model=$inventory->createCatalog('house_models',['code'=>'B','name'=>'Modelo B sintético'],$actor);
            $otherModel=$inventory->createCatalog('house_models',['code'=>'X','name'=>'Modelo X sintético'],$actor);
            $inventory->compatibility($lot,$model,true,$actor);$compat=(int)$inventory->detail($lot)['models'][0]['id'];
            $inventory->revisePrice($lot,null,['amount'=>'100.10'],$actor);
            $inventory->revisePrice($lot,$compat,['land_amount'=>'100.10','construction_amount'=>'50.20'],$actor);
            $input=['seller_id'=>$actor,'purchase_kind'=>'lot','note'=>'Seguimiento sintético de prueba'];
            $lead=$service->interest($lot,$first,$actor,'Primer cliente interesado',$actor);
            $otherLead=$service->interest($lot,$second,$actor,'Segundo cliente interesado',$actor);
            $this->assertSame('available',$inventory->detail($lot)['effective_status']);
            $this->rejects(fn()=>$service->interest($lot,$first,$actor,'Duplicado de interesado',$actor));
            $this->rejects(fn()=>$service->interest($historicalLot,$first,$actor,'Antecedente pendiente',$actor));
            $negotiation=$service->negotiate($lot,$first,$input,$actor);
            $this->assertSame($lead,$negotiation);
            $op=$service->detail($negotiation);
            $this->assertSame('100.10',$op['total_amount']);$this->assertSame('15',(string)$op['negotiation_days']);
            $this->assertSame('negotiating',$inventory->detail($lot)['effective_status']);
            $this->assertSame('interested',$service->detail($otherLead)['state']);
            $this->rejects(fn()=>$service->negotiate($lot,$second,$input,$actor));
            $this->rejects(fn()=>$service->negotiate($missingPriceLot,$second,$input,$actor));
            $inventory->updateSettings(['negotiation_days'=>'20','payment_grace_days'=>'10','reason'=>'Cambio sintético de plazo'],$actor);
            $this->assertSame('15',(string)$service->detail($negotiation)['negotiation_days']);
            $db->table('commercial_operations')->where('id',$negotiation)->update(['expires_at'=>'2000-01-01 00:00:00']);
            $this->assertSame('available',$inventory->detail($lot)['effective_status']);
            $this->assertSame(1,$service->listing('expired')['total']);
            $this->assertSame(0,$service->listing('negotiating')['total']);
            $this->assertSame(1,$service->expireAll());$this->assertSame(0,$service->expireAll());
            $this->assertSame('expired',$service->detail($negotiation)['state']);
            $nextInput=$input;$nextInput['purchase_kind']='lot_house';$nextInput['house_model_id']=$otherModel;
            $this->rejects(fn()=>$service->negotiate($lot,$second,$nextInput,$actor));
            $nextInput['house_model_id']=$model;$nextInput['land_amount']='90.00';$nextInput['price_reason']='Descuento sintético autorizado';
            $this->rejects(fn()=>$service->negotiate($lot,$second,$nextInput,$actor,false));
            $next=$service->negotiate($lot,$second,$nextInput,$actor,true);
            $this->assertSame($otherLead,$next);$op=$service->detail($next);
            $this->assertSame('20',(string)$op['negotiation_days']);$this->assertSame('140.20',$op['total_amount']);$this->assertSame('negotiated',$op['price_source']);
            $revised=$nextInput;$revised['land_amount']='95.00';$revised['lock_version']=$op['lock_version'];
            $service->revise($next,$revised,$actor,true);$op=$service->detail($next);
            $this->assertSame('145.20',$op['total_amount']);
            $this->assertSame($op['expires_at'],$service->detail($next)['expires_at']);
            $this->rejects(fn()=>$service->revise($next,$revised,$actor,true));
            $transition=['lock_version'=>$op['lock_version'],'operation_date'=>\CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateString(),'note'=>'Acuerdo sintético respaldado'];
            $this->rejects(fn()=>$service->transition($next,'reserved',$transition,null,$actor));
            $doc=$this->fakeDocument();$documents[]=$doc;
            $service->transition($next,'reserved',$transition,$doc,$actor);$op=$service->detail($next);
            $this->assertSame('reserved',$op['state']);$this->assertSame('reserved',$inventory->detail($lot)['effective_status']);
            $this->assertSame($second,(int)$clients->propertyFile($lot)['client_id']);
            $this->assertSame('145.20',$service->commitments($second)[0]['agreed_total']);
            $this->rejects(fn()=>$clients->unlink($lot,(int)$clients->propertyFile($lot)['id'],'Intento de separar comprador',$actor));
            $this->rejects(fn()=>$service->revise($next,$revised,$actor,true));
            $db->table('lot_house_prices')->where('lot_house_model_id',$compat)->update(['construction_amount'=>'999.99']);
            $this->assertSame('145.20',$service->detail($next)['total_amount']);
            $saleDoc=$this->fakeDocument();$documents[]=$saleDoc;$transition['lock_version']=$op['lock_version'];
            $service->transition($next,'sold',$transition,$saleDoc,$actor);$op=$service->detail($next);
            $this->assertSame('sold',$op['state']);$this->assertNotNull($op['sold_on']);
            $this->rejects(fn()=>$service->transition($next,'cancelled',['lock_version'=>$op['lock_version'],'note'=>'No se debe anular una venta'],null,$actor));
            $this->assertSame(0,$service->expireAll());
            $history=['seller_id'=>$actor,'state'=>'reserved','operation_date'=>'2026-01-01','purchase_kind'=>'lot','land_amount'=>'500.25','note'=>'Reserva histórica sintética verificada','price_reason'=>'Importe del acuerdo de prueba','verified'=>'1'];
            $historicDoc=$this->fakeDocument();$documents[]=$historicDoc;
            $this->rejects(fn()=>$service->historical($historicalLot,$history,$historicDoc,$actor));
            $clients->link($historicalLot,$first,'Antecedente de expediente sintético',$actor);
            $bad=$history;$bad['verified']='0';$this->rejects(fn()=>$service->historical($historicalLot,$bad,$historicDoc,$actor));
            $this->rejects(fn()=>$service->historical($historicalLot,$history,null,$actor));
            $old=$service->historical($historicalLot,$history,$historicDoc,$actor);$historic=$service->detail($old);
            $this->assertSame('historical',$historic['origin']);$this->assertNull($historic['expires_at']);$this->assertSame('500.25',$historic['total_amount']);
            $this->assertSame('0',(string)$inventory->detail($historicalLot)['import_review_required']);$this->assertSame('reserved',$inventory->detail($historicalLot)['effective_status']);
            $this->rejects(fn()=>$service->historical($historicalLot,$history,$historicDoc,$actor));
            $this->assertSame(1,count($service->detail($old)['documents']));
            $download=$service->downloadable((int)$service->detail($old)['documents'][0]['id']);$this->assertSame($historicDoc['sha256'],hash_file('sha256',$download['path']));
            $this->assertNotEmpty(json_decode($historic['events'][0]['terms_snapshot'],true));

            // A stale repeatable-read snapshot must not expire a reservation committed by another connection.
            $raceLot=$inventory->saveLot(['polygon_id'=>$polygon,'number'=>'03'],$actor);$inventory->revisePrice($raceLot,null,['amount'=>'1.00'],$actor);
            $race=$service->negotiate($raceLot,$first,$input,$actor);
            $db->table('commercial_operations')->where('id',$race)->update(['expires_at'=>'2000-01-01 00:00:00']);
            $db->transBegin();$db->table('commercial_operations')->where('id',$race)->get()->getRowArray();
            $peer=Database::connect('tests',false);
            $peer->transStart();$peer->table('commercial_operations')->where('id',$race)->update(['state'=>'reserved','lock_version'=>1]);$peer->table('lots')->where('id',$raceLot)->update(['commercial_status'=>'reserved']);$peer->transComplete();
            $this->assertSame(0,$service->expireLot($raceLot));$db->transCommit();$peer->close();
            $this->assertSame('reserved',$service->detail($race)['state']);
            $this->assertSame('reserved',$inventory->detail($raceLot)['effective_status']);
            $service->transition($race,'cancelled',['lock_version'=>1,'note'=>'Cancelación sintética de reserva'],null,$actor);
            $this->assertSame('cancelled',$service->detail($race)['state']);
            $this->assertSame('available',$inventory->detail($raceLot)['effective_status']);
            $this->assertSame('500.25',$service->commitments($first)[0]['agreed_total']);
            $this->assertGreaterThan(0,$service->listing('','D-01')['total']);
            $this->assertCount(1,$inventory->inventory(['q'=>'D-01']));

            helper(['auth','setting','asset']);$viewer=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($viewerId);$viewer->addGroup('viewer');$this->actingAs($viewer);
            $this->get('/operaciones')->assertStatus(403);$this->get('/documentos-comerciales/'.$download['id'])->assertStatus(403);
            $this->get('/inventario/'.$lot)->assertDontSee('Cliente comercial Dos');auth()->logout();
            $admin=model(\CodeIgniter\Shield\Models\UserModel::class)->findById($actor);$admin->addGroup('admin');$this->actingAs($admin);
            $this->get('/operaciones')->assertOK();$this->get('/operaciones/'.$old)->assertSee('Antecedente histórico verificado');
            $this->get('/inventario/'.$missingPriceLot.'/operacion')->assertOK();$this->get('/inventario/'.$historicalLot.'/historico')->assertOK();
            $this->get('/clientes/'.$first)->assertSee('500.25');
            $this->get('/documentos-comerciales/'.$download['id'])->assertStatus(200);
        } finally {
            if(function_exists('auth')) auth()->logout();foreach(array_reverse($classes) as $class) (new $class($forge))->down();foreach(array_reverse($settings) as $class) (new $class($forge))->down();$shield->down();
            foreach($documents as $doc) (new CommercialDocuments())->discard($doc);
        }
    }
    private function fakeDocument(): array
    {
        $key=bin2hex(random_bytes(16)).'.pdf';$path=(new CommercialDocuments())->path($key);if(!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
        file_put_contents($path,"%PDF-1.4\n% Synthetic commercial test only\n%%EOF\n");
        return ['storage_key'=>$key,'original_name'=>'respaldo-sintetico.pdf','mime_type'=>'application/pdf','size_bytes'=>filesize($path),'sha256'=>hash_file('sha256',$path)];
    }
    private function rejects(callable $callback): void
    {
        try {$callback();} catch(\DomainException $e) {$this->assertNotEmpty($e->getMessage());return;}$this->fail('Expected a commercial rule rejection.');
    }
}
