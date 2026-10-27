<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\InventoryImportService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class InventoryImport extends BaseController
{
    public function index(): string
    {
        $service=new InventoryImportService();
        return view('imports/index',['title'=>'Importar inventario','installed'=>$service->installed(),'projects'=>db_connect()->table('projects')->where('is_active',1)->get()->getResultArray(),'history'=>$service->history((int)auth()->id())]);
    }
    public function upload()
    {
        if(!$this->validateData($this->request->getPost(),['workbook'=>'uploaded[workbook]|max_size[workbook,5120]|ext_in[workbook,xlsx]','project_id'=>'required|is_natural_no_zero'])) {
            return redirect()->to(site_url('importaciones'))->with('error',implode(' ',$this->validator->getErrors()));
        }
        try {
            $file=$this->request->getFile('workbook');
            $batch=(new InventoryImportService())->preview($file->getTempName(),$file->getClientName(),(int)$this->request->getPost('project_id'),(int)auth()->id());
            return redirect()->to(site_url('importaciones/'.$batch));
        } catch(\DomainException $e) { return redirect()->to(site_url('importaciones'))->with('error',$e->getMessage()); }
        catch(\Throwable $e) { log_message('error','Inventory preview failed: {exception}',['exception'=>$e]);return redirect()->to(site_url('importaciones'))->with('error','No se pudo analizar el archivo. Verifica el formato y la migración 000005.'); }
    }
    public function show(int $id): string
    {
        try { $batch=(new InventoryImportService())->batch($id,(int)auth()->id()); }
        catch(\DomainException $e) { throw PageNotFoundException::forPageNotFound(); }
        return view('imports/show',['title'=>'Revisión del inventario','batch'=>$batch]);
    }
    public function confirm(int $id)
    {
        try {
            $selected=$this->request->getPost('rows');$policies=$this->request->getPost('policies');
            if(!is_array($selected) || ($policies!==null && !is_array($policies))) throw new \DomainException('Revisa la selección de filas y polígonos.');
            $count=(new InventoryImportService())->commit($id,(int)auth()->id(),$selected,$policies??[],$this->request->getPost('acknowledge')==='1');
            return redirect()->to(site_url('importaciones/'.$id))->with('success','Se importaron '.$count.' lotes. No se cargaron clientes ni pagos.');
        } catch(\DomainException $e) { return redirect()->to(site_url('importaciones/'.$id))->with('error',$e->getMessage()); }
        catch(\Throwable $e) { log_message('error','Inventory import failed: {exception}',['exception'=>$e]);return redirect()->to(site_url('importaciones/'.$id))->with('error','No se importó ningún lote. El inventario pudo cambiar; revisa o vuelve a analizar el archivo.'); }
    }
}
