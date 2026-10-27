<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class CommercialService
{
    public const STATES=['interested'=>'Interesado','negotiating'=>'En negociación','reserved'=>'Reservado','sold'=>'Vendido','expired'=>'Negociación vencida','cancelled'=>'Cancelado'];
    private BaseConnection $db;
    public function __construct(?BaseConnection $db=null)
    {
        $this->db=$db??db_connect();
        $offset=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('P');
        $this->db->query('SET SESSION time_zone = ?',[$offset]);
    }
    public function installed(): bool { return $this->db->tableExists($this->db->prefixTable('commercial_operations'),false); }
    public function listing(string $state='',string $q='',int $page=1): array
    {
        $this->ensureInstalled();$builder=$this->base();
        if($state==='negotiating') $builder->where('o.state','negotiating')->where('o.expires_at >',$this->now());
        elseif($state==='expired') $builder->groupStart()->where('o.state','expired')->orGroupStart()->where('o.state','negotiating')->where('o.expires_at <=',$this->now())->groupEnd()->groupEnd();
        elseif(isset(self::STATES[$state])) $builder->where('o.state',$state);
        $q=mb_substr(trim($q),0,100);
        if($q!=='') $builder->groupStart()->like('c.full_name',$q)->orLike('c.code',$q)->orLike('l.number',$q)->orLike('p.code',$q)->orLike("CONCAT(p.code, '-', l.number)",$q)->groupEnd();
        $total=$builder->countAllResults(false);$page=max(1,min($page,max(1,(int)ceil($total/25))));
        return ['rows'=>$builder->orderBy('o.id','DESC')->limit(25,($page-1)*25)->get()->getResultArray(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/25))];
    }
    public function detail(int $id): array
    {
        $this->ensureInstalled();$op=$this->base()->where('o.id',$id)->get()->getRowArray();
        if(!$op) throw new DomainException('No se encontró la operación.');
        $op['events']=$this->db->table('commercial_operation_events')->where('operation_id',$id)->orderBy('id','DESC')->get()->getResultArray();
        $op['documents']=$this->db->table('commercial_operation_documents')->select('id,original_name,mime_type,size_bytes,created_at,uploaded_by')->where('operation_id',$id)->orderBy('id','DESC')->get()->getResultArray();
        $op['is_expired']=$op['state']==='negotiating' && $op['expires_at']<=$this->now();
        return $op;
    }
    public function forLot(int $lot): array { return $this->installed()?$this->base()->where('o.lot_id',$lot)->orderBy('o.id','DESC')->get()->getResultArray():[]; }
    public function forClient(int $client): array { return $this->installed()?$this->base()->where('o.client_id',$client)->orderBy('o.id','DESC')->get()->getResultArray():[]; }
    public function commitments(int $client): array { return $this->installed()?$this->db->table('commercial_operations')->select('currency,SUM(total_amount) AS agreed_total')->where('client_id',$client)->whereIn('state',['reserved','sold'])->groupBy('currency')->get()->getResultArray():[]; }
    public function sellers(): array { return $this->db->table('users')->select('id,username')->where('active',1)->orderBy('username')->get()->getResultArray(); }

    /** Read projection; a stopped scheduler must not show expired negotiations as current. */
    public function decorateInventory(array $lots): array
    {
        if(!$lots || !$this->installed()) return $lots;
        $active=$this->db->table('commercial_operations')->select('id,lot_id,state,expires_at')->whereIn('lot_id',array_column($lots,'id'))->whereIn('state',['negotiating','reserved','sold'])->get()->getResultArray();
        $byLot=[];foreach($active as $op) $byLot[$op['lot_id']]=$op;
        foreach($lots as &$lot) {
            $op=$byLot[$lot['id']]??null;
            if($op && $op['state']==='negotiating' && $op['expires_at']<=$this->now() && $lot['commercial_status']==='negotiating') $lot['commercial_status']='available';
            $lot['commercial_operation_id']=$op['id']??null;
        }
        unset($lot);return $lots;
    }

    public function interest(int $lot,int $client,int $seller,string $note,int $actor): int
    {
        $this->ensureInstalled();$this->reason($note);$this->expireLot($lot);
        return $this->transaction(function() use($lot,$client,$seller,$note,$actor) {
            $property=$this->property($lot);$this->marketable($property);$this->people($client,$seller);
            if($this->db->table('commercial_operations')->where('lot_id',$lot)->where('client_id',$client)->where('state','interested')->countAllResults()) throw new DomainException('Ese cliente ya está registrado como interesado en este lote.');
            $data=['lot_id'=>$lot,'client_id'=>$client,'seller_id'=>$seller,'state'=>'interested','currency'=>$property['currency'],'operation_date'=>$this->today(),'note'=>trim($note),'created_by'=>$actor];
            $this->db->table('commercial_operations')->insert($data);$id=(int)$this->db->insertID();
            $this->event($id,null,'interested',$actor,$note,$this->today());return $id;
        });
    }

    public function negotiate(int $lot,int $client,array $input,int $actor,bool $canOverride=false): int
    {
        $this->ensureInstalled();$this->reason($input['note']??'');$this->expireLot($lot);
        return $this->transaction(function() use($lot,$client,$input,$actor,$canOverride) {
            $property=$this->property($lot);$this->marketable($property);$this->people($client,(int)($input['seller_id']??0));$this->exclusiveFree($lot);
            $setting=$this->current('business_settings','setting_key = ?',['negotiation_days']);
            if(!$setting || (int)$setting['value_days']<1) throw new DomainException('Configura primero los días de negociación.');
            $days=(int)$setting['value_days'];$now=$this->now();
            $terms=$this->terms($lot,$input,false,$canOverride);
            $existing=$this->current('commercial_operations',"lot_id=? AND client_id=? AND state='interested'",[$lot,$client]);
            $data=$terms+['seller_id'=>(int)$input['seller_id'],'state'=>'negotiating','negotiation_days'=>$days,'expires_at'=>(new \DateTimeImmutable($now,new \DateTimeZone(config('App')->appTimezone)))->modify('+'.$days.' days')->format('Y-m-d H:i:s'),'note'=>trim($input['note'])];
            if($existing) { $id=(int)$existing['id'];$data['lock_version']=(int)$existing['lock_version']+1;$this->db->table('commercial_operations')->where('id',$id)->update($data); }
            else { $this->db->table('commercial_operations')->insert($data+['lot_id'=>$lot,'client_id'=>$client,'currency'=>$property['currency'],'operation_date'=>$this->today(),'created_by'=>$actor]);$id=(int)$this->db->insertID(); }
            $this->db->table('lots')->where('id',$lot)->update(['commercial_status'=>'negotiating','lock_version'=>(int)$property['lock_version']+1]);
            $this->event($id,$existing?'interested':null,'negotiating',$actor,$input['note'],$this->today());return $id;
        });
    }

    public function historical(int $lot,array $input,?array $document,int $actor): int
    {
        $this->ensureInstalled();$this->reason($input['note']??'');$date=$this->date($input['operation_date']??'');
        if(($input['verified']??'')!=='1') throw new DomainException('Confirma expresamente la revisión del antecedente y su respaldo.');
        if(!in_array($input['state']??'',['reserved','sold'],true)) throw new DomainException('El antecedente debe ser una reserva o venta.');
        $this->document($document);
        $this->expireLot($lot);
        return $this->transaction(function() use($lot,$input,$document,$actor,$date) {
            $property=$this->property($lot);$this->exclusiveFree($lot);
            if(!in_array($property['commercial_status'],['available','negotiating'],true)) throw new DomainException('El lote ya tiene una operación comercial registrada.');
            $file=$this->current('client_property_links','lot_id=? AND is_active=1',[$lot]);
            if(!$file) throw new DomainException('Vincula primero el cliente del expediente de esta propiedad.');
            $client=(int)$file['client_id'];$this->people($client,(int)($input['seller_id']??0));
            $terms=$this->terms($lot,$input,true,true);$state=$input['state'];
            $data=$terms+['lot_id'=>$lot,'client_id'=>$client,'seller_id'=>(int)$input['seller_id'],'state'=>$state,'origin'=>'historical','currency'=>$property['currency'],'operation_date'=>$date,'reserved_on'=>$state==='reserved'?$date:null,'sold_on'=>$state==='sold'?$date:null,'note'=>trim($input['note']),'created_by'=>$actor];
            $this->db->table('commercial_operations')->insert($data);$id=(int)$this->db->insertID();
            $this->addDocumentRow($id,$document,$actor);
            $this->db->table('lots')->where('id',$lot)->update(['commercial_status'=>$state,'import_review_required'=>0,'lock_version'=>(int)$property['lock_version']+1]);
            $this->event($id,null,$state,$actor,'Antecedente verificado: '.$input['note'],$date);return $id;
        });
    }

    public function revise(int $id,array $input,int $actor,bool $canOverride): void
    {
        $this->ensureInstalled();$lookup=$this->detail($id);$this->expireLot((int)$lookup['lot_id']);$this->reason($input['note']??'');
        $this->transaction(function() use($id,$input,$actor,$canOverride,$lookup) {
            $this->property((int)$lookup['lot_id']);$op=$this->lock('commercial_operations',$id);$this->version($op,$input);
            if($op['state']!=='negotiating' || $op['expires_at']<=$this->now()) throw new DomainException('Solo se pueden revisar condiciones de una negociación vigente.');
            $terms=$this->terms((int)$op['lot_id'],$input,false,$canOverride);
            $this->db->table('commercial_operations')->where('id',$id)->update($terms+['lock_version'=>(int)$op['lock_version']+1]);
            $this->event($id,'negotiating','negotiating',$actor,'Condiciones revisadas: '.$input['note'],$this->today());
        });
    }

    public function transition(int $id,string $target,array $input,?array $document,int $actor): void
    {
        $this->ensureInstalled();$lookup=$this->detail($id);$this->expireLot((int)$lookup['lot_id']);$this->reason($input['note']??'');
        if(!in_array($target,['reserved','sold','cancelled'],true)) throw new DomainException('Transición no permitida.');
        $date=$target==='cancelled'?$this->today():$this->date($input['operation_date']??'');
        if($target!=='cancelled') $this->document($document);
        $this->transaction(function() use($id,$target,$input,$document,$actor,$lookup,$date) {
            $property=$this->property((int)$lookup['lot_id']);$op=$this->lock('commercial_operations',$id);$this->version($op,$input);$old=$op['state'];
            $allowed=($target==='reserved' && $old==='negotiating') || ($target==='sold' && $old==='reserved') || ($target==='cancelled' && in_array($old,['interested','negotiating','reserved'],true));
            if(!$allowed) throw new DomainException('El estado actual no permite esta acción. Una venta requiere un proceso de anulación específico.');
            if($old==='negotiating' && $op['expires_at']<=$this->now()) throw new DomainException('La negociación venció. Inicia una nueva asignación.');
            if($date<($op['reserved_on']??$op['operation_date'])) throw new DomainException('La fecha no puede ser anterior a la etapa previa de esta operación.');
            if($target!=='cancelled') {
                $this->people((int)$op['client_id'],(int)$op['seller_id']);
                if($target==='reserved') { $this->marketable($property);$this->compatibility((int)$op['lot_id'],$op['purchase_kind'],$op['house_model_id']!==null?(int)$op['house_model_id']:null); }
                $this->bindFile((int)$op['lot_id'],(int)$op['client_id'],$actor,$id);
            }
            $data=['state'=>$target,'lock_version'=>(int)$op['lock_version']+1];
            if($target==='reserved') $data['reserved_on']=$date;
            if($target==='sold') $data['sold_on']=$date;
            $this->db->table('commercial_operations')->where('id',$id)->update($data);
            if($target!=='cancelled') $this->addDocumentRow($id,$document,$actor);
            if($old!=='interested') $this->db->table('lots')->where('id',$op['lot_id'])->update(['commercial_status'=>$target==='cancelled'?'available':$target,'lock_version'=>(int)$property['lock_version']+1]);
            $this->event($id,$old,$target,$actor,$input['note'],$date);
        });
    }

    public function attach(int $id,?array $document,int $actor): void
    {
        $this->ensureInstalled();$this->document($document);
        $this->transaction(function() use($id,$document,$actor) { $op=$this->lock('commercial_operations',$id);$this->addDocumentRow($id,$document,$actor);$this->event($id,$op['state'],$op['state'],$actor,'Se agregó un documento de respaldo.',$this->today()); });
    }
    public function downloadable(int $id): array
    {
        $this->ensureInstalled();$row=$this->db->table('commercial_operation_documents')->where('id',$id)->get()->getRowArray();
        if(!$row) throw new DomainException('Documento no disponible.');
        $path=(new CommercialDocuments())->path($row['storage_key']);
        if(!is_file($path) || !hash_equals($row['sha256'],hash_file('sha256',$path))) throw new DomainException('No se pudo verificar el documento.');
        return $row+['path'=>$path];
    }
    public function expireAll(): int
    {
        $this->ensureInstalled();$rows=$this->db->table('commercial_operations')->select('lot_id')->where('state','negotiating')->where('expires_at <=',$this->now())->get()->getResultArray();
        $count=0;foreach($rows as $row) $count+=$this->expireLot((int)$row['lot_id']);return $count;
    }
    public function expireLot(int $lot): int
    {
        if(!$this->installed()) return 0;
        return $this->transaction(function() use($lot) {
            $property=$this->property($lot);$op=$this->current('commercial_operations',"lot_id=? AND state='negotiating' AND expires_at<=?",[$lot,$this->now()]);
            if(!$op) return 0;
            $this->db->table('commercial_operations')->where('id',$op['id'])->update(['state'=>'expired','lock_version'=>(int)$op['lock_version']+1]);
            if($property['commercial_status']==='negotiating') $this->db->table('lots')->where('id',$lot)->update(['commercial_status'=>'available','lock_version'=>(int)$property['lock_version']+1]);
            $this->event((int)$op['id'],'negotiating','expired',null,'Venció el plazo de '.$op['negotiation_days'].' días registrado al iniciar la negociación.',$this->today());return 1;
        });
    }

    private function terms(int $lot,array $input,bool $historical,bool $canOverride): array
    {
        $kind=$input['purchase_kind']??'';if(!in_array($kind,['lot','lot_house'],true)) throw new DomainException('Selecciona solo lote o lote más casa.');
        $model=$kind==='lot_house'?(int)($input['house_model_id']??0):null;$compat=$this->compatibility($lot,$kind,$model);
        $referenceLand=null;$referenceConstruction=null;
        if(!$historical) {
            $table=$kind==='lot'?'lot_prices':'lot_house_prices';$key=$kind==='lot'?'lot_id':'lot_house_model_id';$owner=$kind==='lot'?$lot:$compat['id'];
            $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));
            $prices=$this->db->query("SELECT * FROM $name WHERE $key=? AND valid_from<=? AND (valid_until IS NULL OR valid_until>?) FOR UPDATE",[$owner,$this->now(),$this->now()])->getResultArray();
            if(count($prices)!==1) throw new DomainException('Debe existir una única tarifa vigente para esta selección. Configúrala en la ficha del lote.');
            $referenceLand=CommercialMoney::amount($kind==='lot'?$prices[0]['amount']:$prices[0]['land_amount']);
            $referenceConstruction=CommercialMoney::amount($kind==='lot'?'0':$prices[0]['construction_amount']);
        }
        $land=CommercialMoney::amount(($input['land_amount']??'')!==''?$input['land_amount']:($referenceLand??''));
        $construction=CommercialMoney::amount($kind==='lot'?'0':((($input['construction_amount']??'')!=='')?$input['construction_amount']:($referenceConstruction??'')));
        CommercialMoney::checkTotal($land,$construction);
        $differs=!$historical && ($land!==$referenceLand || $construction!==$referenceConstruction);
        $reason=trim((string)($input['price_reason']??''));
        if($differs && !$canOverride) throw new DomainException('El precio difiere del catálogo. Se requiere permiso para autorizar un precio negociado.');
        if($differs || $historical) $this->reason($reason,500);
        return ['purchase_kind'=>$kind,'house_model_id'=>$model,'land_amount'=>$land,'construction_amount'=>$construction,'reference_land_amount'=>$referenceLand,'reference_construction_amount'=>$referenceConstruction,'price_source'=>$historical?'historical':($differs?'negotiated':'catalog'),'price_reason'=>$reason?:null];
    }
    private function compatibility(int $lot,string $kind,?int $model): ?array
    {
        if($kind==='lot') return null;
        $compat=$this->db->escapeIdentifiers($this->db->prefixTable('lot_house_models'));$models=$this->db->escapeIdentifiers($this->db->prefixTable('house_models'));
        $row=$this->db->query("SELECT c.* FROM $compat c JOIN $models m ON m.id=c.house_model_id WHERE c.lot_id=? AND c.house_model_id=? AND c.is_enabled=1 AND m.is_active=1 FOR UPDATE",[$lot,$model??0])->getRowArray();
        if(!$row) throw new DomainException('Ese modelo no está habilitado para las dimensiones de este lote.');return $row;
    }
    private function bindFile(int $lot,int $client,int $actor,int $operation): void
    {
        $file=$this->current('client_property_links','lot_id=? AND is_active=1',[$lot]);
        if($file && (int)$file['client_id']!==$client) throw new DomainException('El cliente no coincide con el expediente. Corrige el vínculo con motivo antes de confirmar la operación.');
        if(!$file) $this->db->table('client_property_links')->insert(['lot_id'=>$lot,'client_id'=>$client,'note'=>'Vínculo por operación comercial #'.$operation,'linked_by'=>$actor]);
    }
    private function property(int $lot): array
    {
        $initial=$this->db->table('lots')->where('id',$lot)->get()->getRowArray();
        if(!$initial) throw new DomainException('No se encontró el lote.');
        $parent=$this->db->table('polygons')->where('id',$initial['polygon_id'])->get()->getRowArray();
        $project=$this->lock('projects',(int)$parent['project_id']);$polygon=$this->lock('polygons',(int)$initial['polygon_id']);$row=$this->lock('lots',$lot);
        if((int)$row['polygon_id']!==(int)$polygon['id'] || (int)$polygon['project_id']!==(int)$project['id']) throw new DomainException('La ubicación del lote cambió. Recarga la ficha.');
        return $row+['sales_policy'=>$polygon['sales_policy'],'project_active'=>$project['is_active'],'currency'=>$project['currency']];
    }
    private function marketable(array $lot): void
    {
        if(!empty($lot['import_review_required'])) throw new DomainException('Primero verifica el antecedente histórico del expediente.');
        if(!$lot['project_active'] || $lot['sales_policy']==='blocked' || ($lot['sales_policy']==='partial' && !$lot['is_enabled'])) throw new DomainException('La propiedad no está habilitada comercialmente.');
        if(in_array($lot['commercial_status'],['reserved','sold'],true)) throw new DomainException('La propiedad ya está reservada o vendida.');
    }
    private function exclusiveFree(int $lot): void
    {
        if($this->current('commercial_operations','active_lot_id=?',[$lot])) throw new DomainException('Ya existe una negociación asignada, reserva o venta vigente para este lote.');
    }
    private function people(int $client,int $seller): void
    {
        $c=$this->current('clients','id=? AND is_active=1',[$client]);
        $s=$this->current('users','id=? AND active=1',[$seller]);
        if(!$c || !$s) throw new DomainException('Selecciona un cliente y responsable activos.');
    }
    private function document(?array $doc): void
    {
        if(!$doc) throw new DomainException('Adjunta el documento que respalda la operación.');
        $path=(new CommercialDocuments())->path((string)($doc['storage_key']??''));
        if(!is_file($path) || filesize($path)>5*1024*1024 || !hash_equals((string)($doc['sha256']??''),hash_file('sha256',$path))) throw new DomainException('No se pudo verificar el respaldo adjunto.');
    }
    private function addDocumentRow(int $id,array $doc,int $actor): void { $this->db->table('commercial_operation_documents')->insert($doc+['operation_id'=>$id,'uploaded_by'=>$actor]); }
    private function event(int $id,?string $from,string $to,?int $actor,string $reason,string $date): void
    {
        $terms=$this->db->table('commercial_operations')->select('purchase_kind,house_model_id,currency,land_amount,construction_amount,total_amount,price_source,reference_land_amount,reference_construction_amount,price_reason,negotiation_days,expires_at')->where('id',$id)->get()->getRowArray();
        $this->db->table('commercial_operation_events')->insert(['operation_id'=>$id,'from_state'=>$from,'to_state'=>$to,'actor_id'=>$actor,'reason'=>trim($reason),'effective_date'=>$date,'terms_snapshot'=>json_encode($terms,JSON_THROW_ON_ERROR)]);
    }
    private function base(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('commercial_operations o')->select('o.*,l.number,p.code AS polygon_code,j.name AS project_name,c.full_name,c.code AS client_code,u.username AS seller_name,m.name AS model_name')
            ->join('lots l','l.id=o.lot_id')->join('polygons p','p.id=l.polygon_id')->join('projects j','j.id=p.project_id')->join('clients c','c.id=o.client_id')->join('users u','u.id=o.seller_id')->join('house_models m','m.id=o.house_model_id','left');
    }
    private function lock(string $table,int $id): array
    {
        $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));$row=$this->db->query("SELECT * FROM $name WHERE id=? FOR UPDATE",[$id])->getRowArray();
        if(!$row) throw new DomainException('El registro ya no existe.');return $row;
    }
    private function current(string $table,string $where,array $params): ?array
    {
        $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));
        return $this->db->query("SELECT * FROM $name WHERE $where LIMIT 1 FOR UPDATE",$params)->getRowArray();
    }
    private function version(array $op,array $input): void { if((string)$op['lock_version']!==(string)($input['lock_version']??'')) throw new DomainException('La operación cambió. Recarga su ficha antes de continuar.'); }
    private function reason(mixed $reason,int $max=2000): void { if(!is_string($reason) || mb_strlen(trim($reason))<5 || mb_strlen($reason)>$max) throw new DomainException('Describe el motivo o antecedente, entre 5 y '.$max.' caracteres.'); }
    private function date(mixed $date): string
    {
        if(!is_string($date)) throw new DomainException('Ingresa una fecha válida.');
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d || $d->format('Y-m-d')!==$date || $date>$this->today() || $date<'1900-01-01') throw new DomainException('La fecha debe ser válida, no futura y posterior a 1900.');return $date;
    }
    private function today(): string { return substr($this->now(),0,10); }
    private function now(): string { return \CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateTimeString(); }
    private function ensureInstalled(): void { if(!$this->installed()) throw new DomainException('Primero aplica la migración 000007: php spark migrate --all.'); }
    private function transaction(callable $callback): mixed
    {
        $this->db->transException(true)->transBegin();try { $result=$callback();if(!$this->db->transStatus()) throw new \RuntimeException('No se pudo completar la operación.');$this->db->transCommit();return $result; }
        catch(\Throwable $e) { $this->db->transRollback();throw $e; }
    }
}
