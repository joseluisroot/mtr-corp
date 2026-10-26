<?php
declare(strict_types=1);
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class InventoryImportService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db=null)
    {
        $this->db=$db??db_connect();
        $offset=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('P');
        $this->db->query('SET SESSION time_zone = ?',[$offset]);
    }

    public function installed(): bool { return $this->db->tableExists($this->db->prefixTable('inventory_import_batches'),false); }

    public function preview(string $path,string $fileName,int $projectId,int $actor): int
    {
        $this->ensureInstalled();
        $project=$this->db->table('projects')->where('id',$projectId)->where('is_active',1)->get()->getRowArray();
        if(!$project) throw new DomainException('Selecciona un proyecto activo. Puedes crearlo primero en Catálogos.');
        $rows=(new InventoryWorkbookReader())->read($path);
        foreach($rows as &$row) {
            if($row['disposition']==='invalid') continue;
            $existing=$this->findExisting($projectId,$row);
            if($existing) { $row['disposition']='existing';$row['issues'][]='Ya existe un lote con esa identificación en el proyecto; no se modificará.'; }
        }
        unset($row);
        $this->db->transException(true)->transBegin();
        try {
            $this->db->table('inventory_import_batches')->insert(['project_id'=>$projectId,'created_by'=>$actor,'file_name'=>mb_substr(basename(str_replace('\\','/',$fileName)),0,180),'file_sha256'=>hash_file('sha256',$path),'row_count'=>count($rows)]);
            $id=(int)$this->db->insertID();
            foreach($rows as $row) {
                foreach(['source_key'=>80,'polygon_code'=>30,'lot_number'=>30] as $key=>$limit) if(mb_strlen((string)$row[$key])>$limit || !preg_match('/^[A-Z0-9-]+$/',(string)$row[$key])) $row[$key]=null;
                $row['batch_id']=$id;$row['issues']=json_encode(array_values(array_unique($row['issues'])),JSON_THROW_ON_ERROR);
                $this->db->table('inventory_import_rows')->insert($row);
            }
            $this->checkTransaction();$this->db->transCommit();return $id;
        } catch(\Throwable $e) { $this->db->transRollback();throw $e; }
    }

    public function batch(int $id,int $actor): array
    {
        $this->ensureInstalled();
        $batch=$this->db->table('inventory_import_batches b')->select('b.*,p.name AS project_name')->join('projects p','p.id=b.project_id')->where('b.id',$id)->where('b.created_by',$actor)->get()->getRowArray();
        if(!$batch) throw new DomainException('No se encontró una revisión disponible para tu cuenta.');
        $batch['rows']=$this->db->table('inventory_import_rows')->where('batch_id',$id)->orderBy('source_row')->get()->getResultArray();
        $batch['polygons']=$this->db->table('polygons')->where('project_id',$batch['project_id'])->get()->getResultArray();
        return $batch;
    }

    public function history(int $actor): array
    {
        if(!$this->installed()) return [];
        return $this->db->table('inventory_import_batches')->where('created_by',$actor)->orderBy('id','DESC')->limit(20)->get()->getResultArray();
    }

    /** Confirm a previously staged selection atomically; never overwrite live lots. */
    public function commit(int $batchId,int $actor,array $selected,array $policies,bool $acknowledged): int
    {
        $this->ensureInstalled();
        if(!$acknowledged) throw new DomainException('Confirma que revisaste las advertencias y las políticas de los polígonos.');
        if(!$selected || count($selected)>InventoryWorkbookReader::MAX_ROWS) throw new DomainException('Selecciona al menos un lote válido.');
        foreach($selected as $value) if(!is_scalar($value) || !ctype_digit((string)$value)) throw new DomainException('La selección de filas no es válida.');
        $selected=array_values(array_unique(array_map('intval',$selected)));
        $this->db->transException(true)->transBegin();
        try {
            $batch=$this->lock('inventory_import_batches',$batchId);
            if((int)$batch['created_by']!==$actor) throw new DomainException('No puedes confirmar esta revisión.');
            if($batch['status']==='committed') throw new DomainException('Esta revisión ya fue importada. No se volverá a aplicar.');
            $project=$this->lock('projects',(int)$batch['project_id']);
            if(!$project['is_active']) throw new DomainException('El proyecto está inactivo.');
            $rows=$this->db->table('inventory_import_rows')->where('batch_id',$batchId)->whereIn('id',$selected)->get()->getResultArray();
            if(count($rows)!==count($selected)) throw new DomainException('Hay filas que no pertenecen a esta revisión.');
            foreach($rows as $row) {
                if($row['disposition']!=='ready') throw new DomainException('Solo se pueden importar filas marcadas como listas.');
                if($this->findExisting((int)$project['id'],$row)) throw new DomainException('El inventario cambió desde la revisión. Analiza nuevamente el archivo para evitar duplicados.');
            }
            $polygons=[];
            foreach($rows as $row) {
                $code=$row['polygon_code'];
                if(!isset($polygons[$code])) {
                    $polygon=$this->db->table('polygons')->where('project_id',$project['id'])->where('code',$code)->get()->getRowArray();
                    if(!$polygon) {
                        $policy=$policies[$code]??'';
                        if(!is_string($policy) || !in_array($policy,['blocked','partial','enabled'],true)) throw new DomainException('Selecciona una política para cada polígono nuevo.');
                        $data=['project_id'=>$project['id'],'code'=>$code,'name'=>'Polígono '.$code,'sales_policy'=>$policy];
                        $this->db->table('polygons')->insert($data);$polygon=['id'=>$this->db->insertID()]+$data;
                        $this->audit('polygons',(string)$polygon['id'],'import_create',$data,$actor);
                    }
                    $polygons[$code]=$polygon;
                }
                $data=['polygon_id'=>$polygons[$code]['id'],'number'=>$row['lot_number'],'legacy_code'=>$row['source_key'],'commercial_status'=>'available','is_enabled'=>0,'import_review_required'=>(int)$row['review_required'],'legacy_status'=>$row['legacy_status']];
                $this->db->table('lots')->insert($data);$lot=(int)$this->db->insertID();
                $this->db->table('inventory_import_rows')->where('id',$row['id'])->update(['disposition'=>'imported','lot_id'=>$lot]);
                $this->audit('lots',(string)$lot,'import_create',$data+['import_batch_id'=>$batchId,'source_row'=>$row['source_row']],$actor);
            }
            $this->db->table('inventory_import_rows')->where('batch_id',$batchId)->where('disposition','ready')->update(['disposition'=>'omitted']);
            $this->db->table('inventory_import_batches')->where('id',$batchId)->update(['status'=>'committed','imported_count'=>count($rows),'committed_at'=>\CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateTimeString()]);
            $this->audit('inventory_import_batches',(string)$batchId,'commit',['imported_count'=>count($rows),'file_sha256'=>$batch['file_sha256']],$actor);
            $this->checkTransaction();$this->db->transCommit();return count($rows);
        } catch(\Throwable $e) { $this->db->transRollback();throw $e; }
    }

    private function findExisting(int $projectId,array $row): ?array
    {
        return $this->db->table('lots l')->select('l.id')->join('polygons p','p.id=l.polygon_id')->where('p.project_id',$projectId)
            ->groupStart()->groupStart()->where('p.code',$row['polygon_code'])->where('l.number',$row['lot_number'])->groupEnd()->orWhere('l.legacy_code',$row['source_key'])->groupEnd()->get()->getRowArray();
    }
    private function lock(string $table,int $id): array
    {
        $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));
        $row=$this->db->query("SELECT * FROM $name WHERE id=? FOR UPDATE",[$id])->getRowArray();
        if(!$row) throw new DomainException('El registro ya no existe.');return $row;
    }
    private function audit(string $type,string $id,string $action,array $data,int $actor): void
    {
        $this->db->table('inventory_audit')->insert(['actor_id'=>$actor,'entity_type'=>$type,'entity_id'=>$id,'action'=>$action,'after_data'=>json_encode($data,JSON_THROW_ON_ERROR)]);
    }
    private function checkTransaction(): void
    {
        if(!$this->db->transStatus()) throw new \RuntimeException('No se pudo guardar la importación.');
    }
    private function ensureInstalled(): void
    {
        if(!$this->installed()) throw new DomainException('Primero aplica la migración 000005: php spark migrate --all.');
    }
}
