<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class ClientService
{
    private BaseConnection $db;
    public function __construct(?BaseConnection $db=null)
    {
        $this->db=$db??db_connect();
        $offset=(new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('P');
        $this->db->query('SET SESSION time_zone = ?',[$offset]);
    }
    public function installed(): bool { return $this->db->tableExists($this->db->prefixTable('clients'),false); }

    public function search(string $query='',int $page=1,bool $activeOnly=false): array
    {
        $this->ensureInstalled();
        $builder=$this->db->table('clients');$query=mb_substr(trim($query),0,100);
        if($activeOnly) $builder->where('is_active',1);
        if($query!=='') $builder->groupStart()->like('full_name',$query)->orLike('code',$query)->orLike('document_number',$query)->orLike('email',$query)->orLike('phone',$query)->groupEnd();
        $total=$builder->countAllResults(false);$page=max(1,min($page,max(1,(int)ceil($total/25))));
        return ['rows'=>$builder->orderBy('full_name')->orderBy('id')->limit(25,($page-1)*25)->get()->getResultArray(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/25))];
    }
    public function detail(int $id): array
    {
        $this->ensureInstalled();
        $client=$this->db->table('clients')->where('id',$id)->get()->getRowArray();
        if(!$client) throw new DomainException('No se encontró el cliente.');
        $client['properties']=$this->db->table('client_property_links r')->select('r.*,l.number,l.import_review_required,l.legacy_status,p.code AS polygon_code,j.name AS project_name')
            ->join('lots l','l.id=r.lot_id')->join('polygons p','p.id=l.polygon_id')->join('projects j','j.id=p.project_id')->where('r.client_id',$id)->orderBy('r.is_active','DESC')->orderBy('r.id','DESC')->get()->getResultArray();
        return $client;
    }
    public function propertyFile(int $lotId): ?array
    {
        if(!$this->installed()) return null;
        return $this->db->table('client_property_links r')->select('r.*,c.code AS client_code,c.full_name,c.is_active AS client_active')->join('clients c','c.id=r.client_id')->where('r.lot_id',$lotId)->where('r.is_active',1)->get()->getRowArray();
    }

    public function save(array $input,int $actor,?int $id=null): int
    {
        $this->ensureInstalled();
        $this->validate($input,['full_name'=>'required|min_length[3]|max_length[180]','code'=>'permit_empty|max_length[30]|regex_match[/^[A-Za-z0-9-]+$/]','email'=>'permit_empty|valid_email|max_length[180]','phone'=>'permit_empty|max_length[40]','address'=>'permit_empty|max_length[500]','notes'=>'permit_empty|max_length[2000]','document_type'=>'permit_empty|in_list[dui,passport,nit,other]','document_number'=>'permit_empty|max_length[40]|regex_match[/^[A-Za-z0-9 -]+$/]','document_country'=>'permit_empty|exact_length[2]|alpha','is_active'=>'permit_empty|in_list[0,1]']);
        $document=trim((string)($input['document_number']??''));$type=$input['document_type']??'';$country=strtoupper(trim($input['document_country']??''));
        if($document!=='' && ($type==='' || $country==='')) throw new DomainException('Indica tipo y país de emisión del documento.');
        $data=['full_name'=>trim($input['full_name']),'email'=>trim($input['email']??'')?:null,'phone'=>trim($input['phone']??'')?:null,'address'=>trim($input['address']??'')?:null,'notes'=>trim($input['notes']??'')?:null,'document_type'=>$document!==''?$type:null,'document_country'=>$document!==''?$country:null,'document_number'=>$document!==''?strtoupper($document):null,'document_key'=>$document!==''?$country.':'.$type.':'.strtoupper(preg_replace('/[ -]/','',$document)):null,'is_active'=>(int)(($input['is_active']??'1')==='1'),'updated_by'=>$actor];
        return $this->transaction(function() use($data,$input,$actor,$id) {
            $before=$id?$this->lock('clients',$id):null;
            if($before && (int)$before['lock_version']!==(int)($input['lock_version']??-1)) throw new DomainException('Otra persona modificó el cliente. Recarga su ficha antes de guardar.');
            $data['code']=strtoupper(trim($input['code']??''))?:($before['code']??'CLI-'.strtoupper(bin2hex(random_bytes(5))));
            $same=$this->db->table('clients')->groupStart()->where('code',$data['code']);
            if($data['document_key']) $same->orWhere('document_key',$data['document_key']);
            $same->groupEnd();if($id) $same->where('id !=',$id);
            if($same->countAllResults()) throw new DomainException('Ya existe un cliente con ese código o documento. Búscalo antes de crear otro.');
            if($id) { $data['lock_version']=(int)$before['lock_version']+1;$this->db->table('clients')->where('id',$id)->update($data); }
            else { $data['created_by']=$actor;$this->db->table('clients')->insert($data);$id=(int)$this->db->insertID(); }
            $this->audit('clients',(string)$id,$before?'update':'create',['code'=>$data['code'],'changed_fields'=>array_keys($data)],$actor);
            return $id;
        });
    }

    public function link(int $lotId,int $clientId,string $note,int $actor): void
    {
        $this->ensureInstalled();$note=trim($note);
        if(mb_strlen($note)<5 || mb_strlen($note)>500) throw new DomainException('Describe el antecedente del vínculo, entre 5 y 500 caracteres.');
        $this->transaction(function() use($lotId,$clientId,$note,$actor) {
            $this->lock('lots',$lotId);$client=$this->lock('clients',$clientId);
            if(!$client['is_active']) throw new DomainException('El cliente está inactivo.');
            if($this->propertyFile($lotId)) throw new DomainException('El lote ya tiene un cliente de expediente. Desvincúlalo con motivo antes de cambiarlo.');
            $this->db->table('client_property_links')->insert(['lot_id'=>$lotId,'client_id'=>$clientId,'note'=>$note,'linked_by'=>$actor]);
            $this->audit('lots',(string)$lotId,'client_link',['client_id'=>$clientId,'link_id'=>$this->db->insertID()],$actor);
        });
    }
    public function unlink(int $lotId,int $expectedLinkId,string $reason,int $actor): void
    {
        $this->ensureInstalled();$reason=trim($reason);
        if(mb_strlen($reason)<5 || mb_strlen($reason)>500) throw new DomainException('Indica un motivo de desvinculación, entre 5 y 500 caracteres.');
        $this->transaction(function() use($lotId,$expectedLinkId,$reason,$actor) {
            $this->lock('lots',$lotId);$link=$this->propertyFile($lotId);
            if($this->db->tableExists($this->db->prefixTable('commercial_operations'),false) && $this->db->table('commercial_operations')->where('lot_id',$lotId)->whereIn('state',['reserved','sold'])->countAllResults()) throw new DomainException('El cliente forma parte de una reserva o venta vigente. La corrección debe gestionarse en el módulo comercial.');
            if(!$link || (int)$link['id']!==$expectedLinkId) throw new DomainException('El vínculo cambió. Recarga la ficha antes de continuar.');
            $this->db->table('client_property_links')->where('id',$expectedLinkId)->update(['is_active'=>0,'ended_by'=>$actor,'ended_at'=>\CodeIgniter\I18n\Time::now(config('App')->appTimezone)->toDateTimeString(),'end_reason'=>$reason]);
            $this->audit('lots',(string)$lotId,'client_unlink',['link_id'=>$expectedLinkId,'client_id'=>$link['client_id']],$actor);
        });
    }
    private function validate(array $data,array $rules): void
    {
        $v=\Config\Services::validation(null,false);if(!$v->setRules($rules)->run($data)) throw new DomainException(implode(' ',$v->getErrors()));
    }
    private function lock(string $table,int $id): array
    {
        $name=$this->db->escapeIdentifiers($this->db->prefixTable($table));$row=$this->db->query("SELECT * FROM $name WHERE id=? FOR UPDATE",[$id])->getRowArray();
        if(!$row) throw new DomainException('El registro ya no existe.');return $row;
    }
    private function ensureInstalled(): void
    {
        if(!$this->installed()) throw new DomainException('Primero aplica la migración 000006: php spark migrate --all.');
    }
    private function audit(string $type,string $id,string $action,array $data,int $actor): void
    {
        $this->db->table('inventory_audit')->insert(['actor_id'=>$actor,'entity_type'=>$type,'entity_id'=>$id,'action'=>$action,'after_data'=>json_encode($data,JSON_THROW_ON_ERROR)]);
    }
    private function transaction(callable $callback): mixed
    {
        $this->db->transException(true)->transBegin();
        try { $result=$callback();if(!$this->db->transStatus()) throw new \RuntimeException('No se pudo completar la operación.');$this->db->transCommit();return $result; }
        catch(\Throwable $e) { $this->db->transRollback();throw $e; }
    }
}
