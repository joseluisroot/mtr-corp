<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\InventoryService;
use App\Services\InventoryState;
use CodeIgniter\Exceptions\PageNotFoundException;

final class Inventory extends BaseController
{
    private function service(): InventoryService { return new InventoryService(); }
    public function index(): string
    {
        $filters=['q'=>(string)$this->request->getGet('q'),'polygon'=>(string)$this->request->getGet('polygon'),'status'=>(string)$this->request->getGet('status')];
        $rows=$this->service()->inventory($filters);
        $counts=array_fill_keys(array_keys(InventoryState::LABELS),0);
        foreach ($rows as $row) $counts[$row['effective_status']]++;
        return view('inventory/index',['title'=>'Inventario de propiedades','rows'=>$rows,'counts'=>$counts,'filters'=>$filters,'catalogs'=>$this->service()->catalogs(),'labels'=>InventoryState::LABELS]);
    }
    public function show(int $id): string
    {
        try { $lot=$this->service()->detail($id); } catch (\DomainException $e) { throw PageNotFoundException::forPageNotFound(); }
        return view('inventory/show',['title'=>$lot['polygon_code'].'-'.$lot['number'],'lot'=>$lot,'catalogs'=>$this->service()->catalogs(),'labels'=>InventoryState::LABELS]);
    }
    public function form(?int $id=null): string
    {
        try { $lot=$id?$this->service()->detail($id):[]; } catch (\DomainException $e) { throw PageNotFoundException::forPageNotFound(); }
        return view('inventory/form',['title'=>$id?'Editar lote':'Nuevo lote','lot'=>$lot,'catalogs'=>$this->service()->catalogs()]);
    }
    public function save(?int $id=null)
    {
        return $this->mutate(function() use($id) { return 'inventario/'.$this->service()->saveLot($this->request->getPost(),(int)auth()->id(),$id); });
    }
    public function catalogs(): string { return view('inventory/catalogs',['title'=>'Catálogos del proyecto','catalogs'=>$this->service()->catalogs()]); }
    public function createCatalog(string $kind) { return $this->mutate(function() use($kind) { $this->service()->createCatalog($kind,$this->request->getPost(),(int)auth()->id()); return 'catalogos'; }); }
    public function catalogForm(string $kind,int $id): string
    {
        $catalogs=$this->service()->catalogs();
        if (!isset($catalogs[$kind])) throw PageNotFoundException::forPageNotFound();
        $found=array_values(array_filter($catalogs[$kind],fn($row)=>(int)$row['id']===$id));
        if (!$found) throw PageNotFoundException::forPageNotFound();
        return view('inventory/catalog_form',['title'=>'Editar catálogo','kind'=>$kind,'row'=>$found[0],'catalogs'=>$catalogs]);
    }
    public function updateCatalog(string $kind,int $id) { return $this->mutate(function() use($kind,$id) { $this->service()->createCatalog($kind,$this->request->getPost(),(int)auth()->id(),$id);return 'catalogos'; }); }
    public function policy(int $id) { return $this->mutate(function() use($id) { $this->service()->polygonPolicy($id,(string)$this->request->getPost('sales_policy'),(int)auth()->id());return 'catalogos'; }); }
    public function compatibility(int $id) { return $this->mutate(function() use($id) { $this->service()->compatibility($id,(int)$this->request->getPost('house_model_id'),$this->request->getPost('is_enabled')==='1',(int)auth()->id());return 'inventario/'.$id; }); }
    public function price(int $id) { return $this->mutate(function() use($id) { $compat=$this->request->getPost('compatibility_id');$this->service()->revisePrice($id,$compat?(int)$compat:null,$this->request->getPost(),(int)auth()->id());return 'inventario/'.$id; }); }
    public function settings(): string { return view('inventory/settings',['title'=>'Configuración general','settings'=>$this->service()->settings()]); }
    public function saveSettings() { return $this->mutate(function() { $this->service()->updateSettings($this->request->getPost(),(int)auth()->id());return 'configuracion'; }); }
    private function mutate(callable $action)
    {
        try { return redirect()->to(site_url($action()))->with('success','Los cambios se guardaron correctamente.'); }
        catch (\DomainException $e) { return redirect()->back()->withInput()->with('error',$e->getMessage()); }
        catch (\Throwable $e) { log_message('error','Inventory write failed: {exception}',['exception'=>$e]);return redirect()->back()->withInput()->with('error','No se pudo guardar. Revisa los datos o intenta de nuevo.'); }
    }
}
