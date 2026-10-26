<?php
declare(strict_types=1);
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class InventoryService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        // DATETIME values use the business timezone in HTTP, CLI and workers alike.
        $offset=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('P');
        $this->db->query('SET SESSION time_zone = ?',[$offset]);
    }

    public function catalogs(): array
    {
        $result=[];
        foreach (['projects','polygons','lot_types','house_models'] as $table) {
            $result[$table]=$this->db->table($table)->orderBy('id')->get()->getResultArray();
        }
        return $result;
    }

    public function inventory(array $filters=[]): array
    {
        $q=$this->db->table('lots l')->select('l.*, p.code AS polygon_code,p.name AS polygon_name,p.sales_policy,p.project_id,j.name AS project_name,j.currency,j.is_active AS project_active,t.name AS type_name')
            ->join('polygons p','p.id=l.polygon_id')->join('projects j','j.id=p.project_id')->join('lot_types t','t.id=l.lot_type_id','left');
        if (!empty($filters['polygon'])) $q->where('p.id',(int)$filters['polygon']);
        if (!empty($filters['q'])) {
            $search=mb_substr((string)$filters['q'],0,100);
            $q->groupStart()->like('l.number',$search)->orLike('p.code',$search)->orLike('j.name',$search)->groupEnd();
        }
        $rows=$q->orderBy('j.id')->orderBy('p.code')->orderBy('l.number')->get()->getResultArray();
        foreach ($rows as &$row) $row['effective_status']=InventoryState::effective($row);
        unset($row);
        if (!empty($filters['status'])) $rows=array_values(array_filter($rows,fn($r)=>$r['effective_status']===$filters['status']));
        return $rows;
    }

    public function detail(int $id): array
    {
        $rows=array_filter($this->inventory(),fn($r)=>(int)$r['id']===$id);
        if (!$rows) throw new DomainException('No se encontró el lote.');
        $lot=array_values($rows)[0];
        $lot['prices']=$this->db->table('lot_prices')->where('lot_id',$id)->orderBy('valid_from','DESC')->get()->getResultArray();
        $lot['models']=$this->db->table('lot_house_models c')->select('c.*,m.name,m.code,m.is_active AS model_active')->join('house_models m','m.id=c.house_model_id')->where('c.lot_id',$id)->get()->getResultArray();
        foreach ($lot['models'] as &$model) {
            $model['prices']=$this->db->table('lot_house_prices')->where('lot_house_model_id',$model['id'])->orderBy('valid_from','DESC')->get()->getResultArray();
        }
        unset($model);
        $lot['audit']=$this->db->table('inventory_audit')->where('entity_type','lots')->where('entity_id',(string)$id)->orderBy('id','DESC')->limit(15)->get()->getResultArray();
        return $lot;
    }

    public function saveLot(array $input, int $actor, ?int $id=null): int
    {
        $this->validate($input,['polygon_id'=>'required|is_natural_no_zero','lot_type_id'=>'permit_empty|is_natural_no_zero','number'=>'required|max_length[30]|regex_match[/^[A-Za-z0-9-]+$/]','lock_version'=>'permit_empty|is_natural', 'notes'=>'permit_empty|max_length[2000]']);
        $data=['polygon_id'=>(int)$input['polygon_id'],'lot_type_id'=>empty($input['lot_type_id'])?null:(int)$input['lot_type_id'],'number'=>strtoupper(trim($input['number'])),'notes'=>trim($input['notes']??''),'is_enabled'=>($input['is_enabled']??'')==='1'?1:0];
        foreach (['front_m','depth_m','area_m2'] as $key) {
            $value=trim((string)($input[$key]??''));
            if ($value!=='' && (!preg_match('/^\d{1,7}(\.\d{1,3})?$/',$value) || (float)$value<=0)) throw new DomainException('Las dimensiones deben ser positivas, con hasta tres decimales.');
            $data[$key]=$value===''?null:$value;
        }
        return $this->transaction(function() use($data,$actor,$id,$input) {
            $this->exists('polygons',$data['polygon_id']);
            if ($data['lot_type_id']) $this->exists('lot_types',$data['lot_type_id']);
            $before=$id?$this->locked('lots',$id):null;
            if ($before && (int)$before['lock_version']!==(int)($input['lock_version']??-1)) throw new DomainException('Otra persona modificó el lote. Recarga la ficha antes de guardar.');
            if ($before && $before['commercial_status']!=='available') throw new DomainException('Un lote con operación comercial debe modificarse desde su operación.');
            $duplicates=$this->db->table('lots')->where('polygon_id',$data['polygon_id'])->where('number',$data['number']);
            if ($id) $duplicates->where('id !=',$id);
            if ($duplicates->countAllResults()) throw new DomainException('Ese número de lote ya existe en el polígono.');
            if ($id) {
                $data['lock_version']=(int)$before['lock_version']+1;
                $this->db->table('lots')->where('id',$id)->update($data);
            } else {
                $this->db->table('lots')->insert($data);$id=(int)$this->db->insertID();
            }
            $this->audit('lots',(string)$id,$before?'update':'create',$before,$data,$actor);
            return $id;
        });
    }

    public function createCatalog(string $kind,array $input,int $actor,?int $id=null): int
    {
        $rules=['code'=>'required|max_length[30]|regex_match[/^[A-Za-z0-9-]+$/]','name'=>'required|max_length[120]'];
        if (!in_array($kind,['projects','polygons','lot_types','house_models'],true)) throw new DomainException('Catálogo no permitido.');
        if ($kind==='polygons') $rules+=['project_id'=>'required|is_natural_no_zero','sales_policy'=>'required|in_list[blocked,partial,enabled]'];
        $this->validate($input,$rules);
        $data=['code'=>strtoupper(trim($input['code'])),'name'=>trim($input['name'])];
        if ($kind==='polygons') $data+=['project_id'=>(int)$input['project_id'],'sales_policy'=>$input['sales_policy']];
        else $data['is_active']=($input['is_active']??'1')==='1'?1:0;
        if ($kind==='lot_types') {
            foreach (['front_m','depth_m','area_m2'] as $key) {
                $value=trim((string)($input[$key]??''));
                if ($value!=='' && (!preg_match('/^\d{1,7}(\.\d{1,3})?$/',$value) || (float)$value<=0)) throw new DomainException('Las dimensiones deben ser positivas, con hasta tres decimales.');
                $data[$key]=$value===''?null:$value;
            }
        }
        return $this->transaction(function() use($kind,$data,$actor,$id) {
            $before=$id?$this->locked($kind,$id):null;
            if ($before && $kind==='polygons' && (int)$before['project_id']!==$data['project_id']) throw new DomainException('Un polígono registrado no se puede trasladar a otro proyecto.');
            if ($kind==='polygons') $this->exists('projects',$data['project_id']);
            $q=$this->db->table($kind)->where('code',$data['code']);
            if ($kind==='polygons') $q->where('project_id',$data['project_id']);
            if ($id) $q->where('id !=',$id);
            if ($q->countAllResults()) throw new DomainException('El código ya está registrado.');
            if ($id) $this->db->table($kind)->where('id',$id)->update($data);
            else { $this->db->table($kind)->insert($data);$id=(int)$this->db->insertID(); }
            $this->audit($kind,(string)$id,$before?'update':'create',$before,$data,$actor);return $id;
        });
    }

    public function polygonPolicy(int $id,string $policy,int $actor): void
    {
        if (!in_array($policy,['blocked','partial','enabled'],true)) throw new DomainException('Política no válida.');
        $this->transaction(function() use($id,$policy,$actor) {
            $before=$this->locked('polygons',$id);
            $this->db->table('polygons')->where('id',$id)->update(['sales_policy'=>$policy]);
            $this->audit('polygons',(string)$id,'policy',$before,['sales_policy'=>$policy],$actor);
        });
    }

    public function compatibility(int $lotId,int $modelId,bool $enabled,int $actor): void
    {
        $this->transaction(function() use($lotId,$modelId,$enabled,$actor) {
            $this->locked('lots',$lotId);$this->exists('house_models',$modelId);
            $before=$this->db->table('lot_house_models')->where('lot_id',$lotId)->where('house_model_id',$modelId)->get()->getRowArray();
            $data=['lot_id'=>$lotId,'house_model_id'=>$modelId,'is_enabled'=>(int)$enabled];
            if ($before) $this->db->table('lot_house_models')->where('id',$before['id'])->update($data);
            else $this->db->table('lot_house_models')->insert($data);
            $this->audit('lots',(string)$lotId,'compatibility',$before,$data,$actor);
        });
    }

    /** A price revision starts now; previous version is retained with an exclusive end. */
    public function revisePrice(int $lotId,?int $compatId,array $input,int $actor): void
    {
        $fields=$compatId?['land_amount','construction_amount']:['amount'];
        foreach ($fields as $field) if (!isset($input[$field]) || !preg_match('/^\d{1,12}(\.\d{1,2})?$/',(string)$input[$field])) throw new DomainException('Ingresa precios no negativos, sin separadores de miles y con hasta dos decimales.');
        $this->transaction(function() use($lotId,$compatId,$input,$fields,$actor) {
            $this->locked('lots',$lotId);
            if ($compatId) {
                $compat=$this->locked('lot_house_models',$compatId);
                if ((int)$compat['lot_id']!==$lotId || !$compat['is_enabled']) throw new DomainException('Selecciona un modelo habilitado para este lote.');
                $model=$this->exists('house_models',(int)$compat['house_model_id']);
                if (!$model['is_active']) throw new DomainException('El modelo está inactivo.');
            }
            $table=$compatId?'lot_house_prices':'lot_prices';$key=$compatId?'lot_house_model_id':'lot_id';$owner=$compatId??$lotId;
            $now=$this->now();
            $previous=$this->db->table($table)->where($key,$owner)->orderBy('valid_from','DESC')->get()->getRowArray();
            if ($previous && $previous['valid_from'] >= $now) throw new DomainException('Ya existe una revisión para este instante o una tarifa futura. Revisa el historial antes de continuar.');
            if ($previous && ($previous['valid_until']===null || $previous['valid_until']>$now)) $this->db->table($table)->where('id',$previous['id'])->update(['valid_until'=>$now]);
            $data=[$key=>$owner,'valid_from'=>$now,'created_by'=>$actor];
            foreach ($fields as $field) $data[$field]=$input[$field];
            $this->db->table($table)->insert($data);
            $this->audit('lots',(string)$lotId,'price_revision',$previous,$data,$actor);
        });
    }

    public function settings(): array
    {
        return $this->db->table('business_settings')->orderBy('setting_key')->get()->getResultArray();
    }

    public function updateSettings(array $input,int $actor): void
    {
        $this->validate($input,['negotiation_days'=>'required|is_natural_no_zero|less_than_equal_to[3650]','payment_grace_days'=>'required|is_natural|less_than_equal_to[3650]','reason'=>'required|min_length[5]|max_length[500]']);
        $this->transaction(function() use($input,$actor) {
            foreach (['negotiation_days','payment_grace_days'] as $key) {
                $table=$this->db->escapeIdentifiers($this->db->prefixTable('business_settings'));
                $before=$this->db->query("SELECT * FROM $table WHERE setting_key = ? FOR UPDATE",[$key])->getRowArray();
                if (!$before) throw new DomainException('Faltan parámetros iniciales. Ejecuta InitialSettingsSeeder.');
                if ((int)$before['value_days']===(int)$input[$key]) continue;
                $this->db->table('business_settings')->where('setting_key',$key)->update(['value_days'=>(int)$input[$key],'updated_by'=>$actor]);
                $this->db->table('business_setting_history')->insert(['setting_key'=>$key,'old_value_days'=>$before['value_days'],'new_value_days'=>(int)$input[$key],'effective_at'=>$this->now(),'changed_by'=>$actor,'reason'=>$input['reason']]);
                $this->audit('business_settings',$key,'update',$before,['value_days'=>(int)$input[$key],'reason'=>$input['reason']],$actor);
            }
        });
    }

    private function validate(array $input,array $rules): void
    {
        $validator=\Config\Services::validation(null,false);
        if (!$validator->setRules($rules)->run($input)) throw new DomainException(implode(' ', $validator->getErrors()));
    }
    private function now(): string
    {
        return \CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateTimeString();
    }
    private function exists(string $table,int $id): array
    {
        $row=$this->db->table($table)->where('id',$id)->get()->getRowArray();
        if (!$row) throw new DomainException('El registro seleccionado ya no existe.');return $row;
    }
    private function locked(string $table,int $id): array
    {
        $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));
        $row=$this->db->query("SELECT * FROM $name WHERE id = ? FOR UPDATE",[$id])->getRowArray();
        if (!$row) throw new DomainException('El registro seleccionado ya no existe.');return $row;
    }
    private function audit(string $type,string $id,string $action,?array $before,array $after,int $actor): void
    {
        $this->db->table('inventory_audit')->insert(['actor_id'=>$actor,'entity_type'=>$type,'entity_id'=>$id,'action'=>$action,'before_data'=>$before?json_encode($before,JSON_THROW_ON_ERROR):null,'after_data'=>json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    private function transaction(callable $action): mixed
    {
        $this->db->transException(true)->transBegin();
        try { $result=$action(); if (!$this->db->transStatus()) throw new \RuntimeException('No se pudo completar la operación.'); $this->db->transCommit(); return $result; }
        catch (\Throwable $e) { $this->db->transRollback();throw $e; }
    }
}
