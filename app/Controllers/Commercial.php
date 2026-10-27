<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\CommercialService;
use App\Services\CommercialDocuments;
use App\Services\ClientService;
use App\Services\InventoryService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class Commercial extends BaseController
{
    public function index(): string
    {
        $s=new CommercialService();$state=(string)$this->request->getGet('state');$q=(string)$this->request->getGet('q');
        return view('commercial/index',['title'=>'Operaciones comerciales','installed'=>$s->installed(),'state'=>$state,'q'=>$q,'states'=>CommercialService::STATES,'result'=>$s->installed()?$s->listing($state,$q,(int)$this->request->getGet('page')):['rows'=>[],'total'=>0,'page'=>1,'pages'=>1]]);
    }
    public function form(int $lot,string $mode='new'): string
    {
        $s=new CommercialService();if(!$s->installed()) return $this->index();
        try { $property=(new InventoryService())->detail($lot); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        $historical=$mode==='historico';$query=(string)$this->request->getGet('q');
        return view('commercial/form',['title'=>$historical?'Verificar antecedente histórico':'Nueva negociación','historical'=>$historical,'lot'=>$property,'sellers'=>$s->sellers(),'clients'=>(new ClientService())->search($query,1,true),'q'=>$query,'file'=>(new ClientService())->propertyFile($lot),'op'=>null]);
    }
    public function edit(int $id): string
    {
        try { $op=(new CommercialService())->detail($id);$lot=(new InventoryService())->detail((int)$op['lot_id']); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        return view('commercial/form',['title'=>'Revisar condiciones','historical'=>false,'lot'=>$lot,'op'=>$op,'sellers'=>[],'clients'=>['rows'=>[]],'q'=>'','file'=>null]);
    }
    public function show(int $id): string
    {
        try { $op=(new CommercialService())->detail($id); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        return view('commercial/show',['title'=>'Operación #'.$id,'op'=>$op,'states'=>CommercialService::STATES]);
    }
    public function negotiate(int $lot)
    {
        return $this->write(function() use($lot) {
            $s=new CommercialService();$intent=$this->request->getPost('intent');
            if($intent==='interested') $id=$s->interest($lot,(int)$this->request->getPost('client_id'),(int)$this->request->getPost('seller_id'),(string)$this->request->getPost('note'),(int)auth()->id());
            elseif($intent==='negotiating') $id=$s->negotiate($lot,(int)$this->request->getPost('client_id'),$this->request->getPost(),(int)auth()->id(),auth()->user()->can('commercial.price_override'));
            else throw new \DomainException('Selecciona interesado o negociación asignada.');
            return 'operaciones/'.$id;
        });
    }
    public function interest(int $lot)
    {
        return $this->write(function() use($lot) { $id=(new CommercialService())->interest($lot,(int)$this->request->getPost('client_id'),(int)$this->request->getPost('seller_id'),(string)$this->request->getPost('note'),(int)auth()->id());return 'operaciones/'.$id; });
    }
    public function historical(int $lot)
    {
        return $this->write(function($doc) use($lot) { $id=(new CommercialService())->historical($lot,$this->request->getPost(),$doc,(int)auth()->id());return 'operaciones/'.$id; },true);
    }
    public function revise(int $id) { return $this->write(function() use($id) { (new CommercialService())->revise($id,$this->request->getPost(),(int)auth()->id(),auth()->user()->can('commercial.price_override'));return 'operaciones/'.$id; }); }
    public function transition(int $id,string $state)
    {
        return $this->write(function($doc) use($id,$state) { (new CommercialService())->transition($id,$state,$this->request->getPost(),$doc,(int)auth()->id());return 'operaciones/'.$id; },$state!=='cancelled');
    }
    public function attach(int $id) { return $this->write(function($doc) use($id) { (new CommercialService())->attach($id,$doc,(int)auth()->id());return 'operaciones/'.$id; },true); }
    public function download(int $id)
    {
        try { $doc=(new CommercialService())->downloadable($id); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        return $this->response->download($doc['path'],null)->setFileName($doc['original_name'])->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff');
    }
    private function write(callable $callback,bool $withDocument=false)
    {
        $doc=null;$store=new CommercialDocuments();
        try { if($withDocument) $doc=$store->store($this->request->getFile('support'));$url=$callback($doc);return redirect()->to(site_url($url))->with('success','La operación se guardó correctamente.'); }
        catch(\DomainException $e) { $store->discard($doc);return redirect()->back()->withInput()->with('error',$e->getMessage()); }
        catch(\Throwable) { $store->discard($doc);log_message('error','Commercial operation failed for actor {actor}.',['actor'=>auth()->id()]);return redirect()->back()->withInput()->with('error','No se guardó la operación. Revisa los datos, la migración 000007 y posibles cambios simultáneos.'); }
    }
}
