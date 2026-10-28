<?php

declare(strict_types=1);

namespace SanadAi\Modules;

use PDO;
use InvalidArgumentException;
use SanadAi\Http\NotFoundException;
use SanadAi\Auth\SessionContext;
use SanadAi\AI\LocalReportGenerator;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Support\Uuid;
use SanadAi\Tenancy\TenantContext;

final class ModuleController
{
    public function __construct(
        private PDO $pdo,
        private TenantContext $tenantContext,
        private SessionContext $sessionContext,
        private Response $response,
    ) {}

    public function dashboard(Request $request): void
    {
        $tenant = $this->tenant();
        $data = [
            'payments' => (int)$this->scalar("SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant AND status = 'paid'", ['tenant'=>$tenant]),
            'pending_payments' => (int)$this->scalar("SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant AND status = 'pending'", ['tenant'=>$tenant]),
            'invoices_unpaid' => (int)$this->scalar("SELECT COUNT(*) FROM invoices WHERE tenant_id = :tenant AND status IN ('issued','overdue')", ['tenant'=>$tenant]),
            'attendance_today' => (int)$this->scalar("SELECT COUNT(*) FROM attendance WHERE tenant_id = :tenant AND attendance_date = CURDATE()", ['tenant'=>$tenant]),
            'clearance_open' => (int)$this->scalar("SELECT COUNT(*) FROM clearance_operations WHERE tenant_id = :tenant AND status NOT IN ('completed','cancelled')", ['tenant'=>$tenant]),
            'unread_notifications' => (int)$this->scalar("SELECT COUNT(*) FROM notifications WHERE tenant_id = :tenant AND read_at IS NULL", ['tenant'=>$tenant]),
            'whatsapp_open' => (int)$this->scalar("SELECT COUNT(*) FROM whatsapp_conversations WHERE tenant_id = :tenant AND status = 'open'", ['tenant'=>$tenant]),
            'revenue_baisa' => (int)$this->scalar("SELECT COALESCE(SUM(amount_baisa),0) FROM payments WHERE tenant_id = :tenant AND status = 'paid' AND type = 'payment'", ['tenant'=>$tenant]),
        ];
        $this->response->json(['success'=>true,'data'=>$data]);
    }

    public function list(Request $request, string $module): void
    {
        $tenant=$this->tenant(); $map=$this->map();
        if(!isset($map[$module])) throw new NotFoundException('Unknown module.');
        [$table,$columns]=$map[$module];
        $limit=min(max((int)$request->query('limit',30),1),100); $offset=max((int)$request->query('offset',0),0);
        $where=['tenant_id=:tenant']; $params=['tenant'=>$tenant]; if($module==='notifications'){ $where[]='(user_id=:current_user OR user_id IS NULL)'; $params['current_user']=$this->sessionContext->userId(); } $q=trim((string)$request->query('q',''));
        if($q!==''){
            $searchable=array_intersect(['title','body','message','reference','invoice_number','external_reference','phone','authority','notes'],$columns);
            if($searchable){$parts=[];foreach($searchable as $i=>$column){$key='q'.$i;$parts[]="$column LIKE :$key";$params[$key]="%$q%";}$where[]='('.implode(' OR ',$parts).')';}
        }
        $status=trim((string)$request->query('status','')); if($status!==''&&in_array('status',$columns,true)){$where[]='status=:status';$params['status']=$status;}
        $sql='SELECT '.implode(',',$columns)." FROM $table WHERE ".implode(' AND ',$where).' ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $st=$this->pdo->prepare($sql);foreach($params as $k=>$v)$st->bindValue(':'.$k,$v);$st->bindValue(':limit',$limit,PDO::PARAM_INT);$st->bindValue(':offset',$offset,PDO::PARAM_INT);$st->execute();
        $rows=array_map([$this,'publicRow'],$st->fetchAll(PDO::FETCH_ASSOC));$count=$this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE ".implode(' AND ',$where));foreach($params as $k=>$v)$count->bindValue(':'.$k,$v);$count->execute();
        $this->response->json(['success'=>true,'data'=>$rows,'meta'=>['total'=>(int)$count->fetchColumn(),'limit'=>$limit,'offset'=>$offset]]);
    }

    public function store(Request $request,string $module):void
    {
        $tenant=$this->tenant();$input=$request->json();$map=$this->map();if(!isset($map[$module]))throw new NotFoundException('Unknown module.');[$table,$allowed]=$map[$module];$data=[];
        foreach($allowed as $column){if(in_array($column,['id','public_id','tenant_id','created_at','updated_at'],true))continue;if(array_key_exists($column,$input))$data[$column]=$input[$column];}
        $data=$this->sanitize($data,$tenant,false);
        $data['public_id']=Uuid::v4();$data['tenant_id']=$tenant;$data['created_at']=date('Y-m-d H:i:s');
        if(in_array('created_by',$allowed,true))$data['created_by']=$this->sessionContext->userId();
        if(in_array('generated_by',$allowed,true))$data['generated_by']=$this->sessionContext->userId();
        if(in_array('user_id',$allowed,true))$data['user_id']=$this->sessionContext->userId();
        if(in_array('status',$allowed,true)&&empty($data['status']))$data['status']=$this->defaultStatus($module);
        $columns=array_keys($data);$ph=array_map(fn($c)=>':'.$c,$columns);$st=$this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',$ph).')');foreach($data as $k=>$v)$st->bindValue(':'.$k,$v);$st->execute();
        $id=(int)$this->pdo->lastInsertId();$this->audit('create',$module,$id,$data);$this->response->json(['success'=>true,'data'=>$this->publicRow($this->find($table,$id,$tenant))],201);
    }

    public function update(Request $request,string $module,string $publicId):void
    {
        $tenant=$this->tenant();$input=$request->json();$map=$this->map();if(!isset($map[$module]))throw new NotFoundException('Unknown module.');[$table,$allowed]=$map[$module];$data=[];
        foreach($allowed as $column){if(in_array($column,['id','public_id','tenant_id','created_at'],true))continue;if(array_key_exists($column,$input))$data[$column]=$input[$column];}
        $data=$this->sanitize($data,$tenant,true);if(!$data)throw new InvalidArgumentException('No fields to update.');$data['updated_at']=date('Y-m-d H:i:s');$sets=[];foreach(array_keys($data) as $c)$sets[]="$c=:$c";
        $st=$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE tenant_id=:tenant AND public_id=:public_id LIMIT 1');foreach($data as $k=>$v)$st->bindValue(':'.$k,$v);$st->bindValue(':tenant',$tenant);$st->bindValue(':public_id',$publicId);$st->execute();if($st->rowCount()===0)throw new NotFoundException('Record not found.');
        $row=$this->findByPublicId($table,$publicId,$tenant);$this->audit('update',$module,(int)$row['id'],$data);$this->response->json(['success'=>true,'data'=>$this->publicRow($row)]);
    }

    public function markRead(Request $request,string $publicId):void
    {
        $tenant=$this->tenant();$st=$this->pdo->prepare('UPDATE notifications SET read_at=:read_at WHERE tenant_id=:tenant AND public_id=:public_id AND user_id=:user');$st->execute(['read_at'=>date('Y-m-d H:i:s'),'tenant'=>$tenant,'public_id'=>$publicId,'user'=>$this->sessionContext->userId()]);$this->response->json(['success'=>true]);
    }

    public function generateAiReport(Request $request): void
    {
        $tenant = $this->tenant();
        $from = (string)$request->query('from', date('Y-m-01'));
        $to = (string)$request->query('to', date('Y-m-d'));
        $params = ['tenant'=>$tenant,'from'=>$from,'to'=>$to];
        $stats = [
            'transactions'=>(int)$this->scalar("SELECT COUNT(*) FROM transactions WHERE tenant_id=:tenant AND DATE(created_at) BETWEEN :from AND :to",$params),
            'completed'=>(int)$this->scalar("SELECT COUNT(*) FROM transactions WHERE tenant_id=:tenant AND status='completed' AND DATE(updated_at) BETWEEN :from AND :to",$params),
            'revenue_baisa'=>(int)$this->scalar("SELECT COALESCE(SUM(amount_baisa),0) FROM payments WHERE tenant_id=:tenant AND status='paid' AND DATE(created_at) BETWEEN :from AND :to",$params),
            'new_clients'=>(int)$this->scalar("SELECT COUNT(*) FROM clients WHERE tenant_id=:tenant AND DATE(created_at) BETWEEN :from AND :to",$params),
            'open_clearance'=>(int)$this->scalar("SELECT COUNT(*) FROM clearance_operations WHERE tenant_id=:tenant AND status NOT IN ('completed','cancelled')",['tenant'=>$tenant]),
        ];
        $output = (new LocalReportGenerator())->generate($stats);
        $st=$this->pdo->prepare('INSERT INTO ai_reports (public_id,tenant_id,report_type,prompt_summary,output,status,generated_by,created_at,updated_at) VALUES (:public_id,:tenant,:type,:summary,:output,:status,:user,:created_at,:updated_at)');
        $st->execute(['public_id'=>Uuid::v4(),'tenant'=>$tenant,'type'=>'operational_summary','summary'=>"Period {$from} to {$to}",'output'=>$output,'status'=>'completed','user'=>$this->sessionContext->userId(),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $this->response->json(['success'=>true,'data'=>['stats'=>$stats,'output'=>$output]]);
    }

    public function report(Request $request):void
    {
        $tenant=$this->tenant();$from=(string)$request->query('from',date('Y-m-01'));$to=(string)$request->query('to',date('Y-m-d'));
        $p=['tenant'=>$tenant,'from'=>$from,'to'=>$to];$data=['from'=>$from,'to'=>$to,'transactions'=>(int)$this->scalar("SELECT COUNT(*) FROM transactions WHERE tenant_id=:tenant AND DATE(created_at) BETWEEN :from AND :to",$p),'completed'=>(int)$this->scalar("SELECT COUNT(*) FROM transactions WHERE tenant_id=:tenant AND status='completed' AND DATE(updated_at) BETWEEN :from AND :to",$p),'revenue_baisa'=>(int)$this->scalar("SELECT COALESCE(SUM(amount_baisa),0) FROM payments WHERE tenant_id=:tenant AND status='paid' AND DATE(created_at) BETWEEN :from AND :to",$p),'new_clients'=>(int)$this->scalar("SELECT COUNT(*) FROM clients WHERE tenant_id=:tenant AND DATE(created_at) BETWEEN :from AND :to",$p),'open_clearance'=>(int)$this->scalar("SELECT COUNT(*) FROM clearance_operations WHERE tenant_id=:tenant AND status NOT IN ('completed','cancelled')",['tenant'=>$tenant])];
        $this->response->json(['success'=>true,'data'=>$data]);
    }


    /** Strip internal identifiers before returning a row to the client. */
    private function publicRow(array $row): array
    {
        unset($row['id'], $row['tenant_id']);
        return $row;
    }

    /**
     * Drop server-controlled columns and resolve foreign references (UUID or numeric id)
     * to rows that belong to the current tenant. Prevents cross-tenant references and spoofing.
     */
    private function sanitize(array $data, int $tenant, bool $isUpdate): array
    {
        unset($data['created_by'], $data['generated_by'], $data['read_at']);
        if ($isUpdate) { unset($data['user_id']); }
        $tables = ['client_id' => 'clients', 'transaction_id' => 'transactions'];
        foreach ($tables as $column => $table) {
            if (!array_key_exists($column, $data)) { continue; }
            if ($data[$column] === null || $data[$column] === '') { $data[$column] = null; continue; }
            $ref = (string)$data[$column];
            $field = ctype_digit($ref) ? 'id' : 'public_id';
            $st = $this->pdo->prepare("SELECT id FROM $table WHERE tenant_id=:t AND $field=:v LIMIT 1");
            $st->execute(['t' => $tenant, 'v' => $ref]);
            $id = $st->fetchColumn();
            if ($id === false) { throw new InvalidArgumentException("Unknown $column for this tenant."); }
            $data[$column] = (int)$id;
        }
        return $data;
    }

    private function tenant():int{$id=$this->tenantContext->get();if(!$id)throw new \SanadAi\Http\ForbiddenException('Tenant context is required.');return $id;}
    private function scalar(string $sql,array $params):mixed{$st=$this->pdo->prepare($sql);$st->execute($params);return $st->fetchColumn();}
    private function find(string $table,int $id,int $tenant):array{$st=$this->pdo->prepare("SELECT * FROM $table WHERE id=:id AND tenant_id=:tenant LIMIT 1");$st->execute(['id'=>$id,'tenant'=>$tenant]);return $st->fetch(PDO::FETCH_ASSOC)?:[];}
    private function findByPublicId(string $table,string $publicId,int $tenant):array{$st=$this->pdo->prepare("SELECT * FROM $table WHERE public_id=:public_id AND tenant_id=:tenant LIMIT 1");$st->execute(['public_id'=>$publicId,'tenant'=>$tenant]);return $st->fetch(PDO::FETCH_ASSOC)?:[];}
    private function defaultStatus(string $module):string{return match($module){ 'payments'=>'pending','invoices'=>'draft','attendance'=>'present','clearance'=>'open','whatsapp'=>'open','ai_reports'=>'queued',default=>'open'};}
    private function audit(string $action,string $entity,int $id,array $meta):void{$st=$this->pdo->prepare('INSERT INTO audit_logs (public_id,tenant_id,user_id,action,entity_type,entity_id,metadata,ip_address,created_at) VALUES (:public_id,:tenant,:user,:action,:entity_type,:entity_id,:metadata,:ip,:created_at)');$st->execute(['public_id'=>Uuid::v4(),'tenant'=>$this->tenant(),'user'=>$this->sessionContext->userId(),'action'=>$action,'entity_type'=>$entity,'entity_id'=>$id,'metadata'=>json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'ip'=>$_SERVER['REMOTE_ADDR']??'127.0.0.1','created_at'=>date('Y-m-d H:i:s')]);}
    private function map():array{return [
        'payments'=>['payments',['id','public_id','tenant_id','transaction_id','client_id','type','amount_baisa','method','reference','status','paid_at','notes','created_by','created_at','updated_at']],
        'invoices'=>['invoices',['id','public_id','tenant_id','transaction_id','client_id','invoice_number','subtotal_baisa','official_fee_baisa','office_fee_baisa','total_baisa','status','due_at','notes','created_by','created_at','updated_at']],
        'attendance'=>['attendance',['id','public_id','tenant_id','user_id','attendance_date','check_in','check_out','status','notes','created_at','updated_at']],
        'clearance'=>['clearance_operations',['id','public_id','tenant_id','transaction_id','authority','external_reference','status','submitted_at','completed_at','notes','created_by','created_at','updated_at']],
        'discussions'=>['discussions',['id','public_id','tenant_id','transaction_id','user_id','channel','message','is_ai','created_at','updated_at']],
        'notifications'=>['notifications',['id','public_id','tenant_id','user_id','type','title','body','read_at','created_at','updated_at']],
        'whatsapp'=>['whatsapp_conversations',['id','public_id','tenant_id','client_id','transaction_id','phone','direction','message_type','body','media_path','status','created_by','created_at','updated_at']],
        'ai_reports'=>['ai_reports',['id','public_id','tenant_id','report_type','prompt_summary','output','status','generated_by','created_at','updated_at']],
    ];}
}
