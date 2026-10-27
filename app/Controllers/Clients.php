<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\ClientService;
use App\Services\InventoryService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class Clients extends BaseController
{
    public function index(): string
    {
        $service=new ClientService();$q=(string)$this->request->getGet('q');
        return view('clients/index',['title'=>'Clientes','installed'=>$service->installed(),'q'=>$q,'result'=>$service->installed()?$service->search($q,(int)$this->request->getGet('page')):['rows'=>[],'total'=>0,'page'=>1,'pages'=>1]]);
    }
    public function form(?int $id=null): string
    {
        $service=new ClientService();if(!$service->installed()) return $this->index();
        try { $client=$id?$service->detail($id):[]; } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        return view('clients/form',['title'=>$id?'Editar cliente':'Nuevo cliente','client'=>$client,'returnLot'=>max(0,(int)$this->request->getGet('return_lot'))]);
    }
    public function show(int $id): string
    {
        try { $client=(new ClientService())->detail($id); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        $commercial=new \App\Services\CommercialService();
        return view('clients/show',['title'=>$client['full_name'],'client'=>$client,'commercialOperations'=>auth()->user()->can('commercial.view')?$commercial->forClient($id):[],'commitments'=>auth()->user()->can('commercial.view')?$commercial->commitments($id):[]]);
    }
    public function save(?int $id=null)
    {
        return $this->mutate(function() use($id) {
            $client=(new ClientService())->save($this->request->getPost(),(int)auth()->id(),$id);
            $returnLot=max(0,(int)$this->request->getPost('return_lot'));
            if(!$id && $returnLot && auth()->user()->can('clients.link')) return 'inventario/'.$returnLot.'/cliente';
            return 'clientes/'.$client;
        });
    }
    public function choose(int $lotId)
    {
        $service=new ClientService();if(!$service->installed()) return redirect()->to(site_url('clientes'));
        try { $lot=(new InventoryService())->detail($lotId); } catch(\DomainException) { throw PageNotFoundException::forPageNotFound(); }
        $q=(string)$this->request->getGet('q');
        return view('clients/choose',['title'=>'Vincular cliente al expediente','lot'=>$lot,'q'=>$q,'result'=>$service->search($q,(int)$this->request->getGet('page'),true),'current'=>$service->propertyFile($lotId)]);
    }
    public function link(int $lotId)
    {
        return $this->mutate(function() use($lotId) { (new ClientService())->link($lotId,(int)$this->request->getPost('client_id'),(string)$this->request->getPost('note'),(int)auth()->id());return 'inventario/'.$lotId; });
    }
    public function unlink(int $lotId)
    {
        return $this->mutate(function() use($lotId) { (new ClientService())->unlink($lotId,(int)$this->request->getPost('link_id'),(string)$this->request->getPost('reason'),(int)auth()->id());return 'inventario/'.$lotId; });
    }
    private function mutate(callable $callback)
    {
        try { return redirect()->to(site_url($callback()))->with('success','Los cambios se guardaron correctamente.'); }
        catch(\DomainException $e) { return redirect()->back()->withInput()->with('error',$e->getMessage()); }
        catch(\Throwable) { log_message('error','Client operation failed; actor {actor}.',['actor'=>auth()->id()]);return redirect()->back()->withInput()->with('error','No se pudo guardar. Revisa duplicados, permisos y la migración 000006.'); }
    }
}
