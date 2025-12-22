<?php

namespace app\admin\controller\user;

use Throwable;
use app\admin\model\User;
use app\admin\model\UserMoneyLog;
use app\common\controller\Backend;
use think\facade\Db;
use app\services\CardService;
use think\facade\Queue;
use think\facade\Cache;

class Recharge extends Backend
{
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['create_time'];

    protected string|array $quickSearchField = ['user.username', 'user.nickname'];

    protected array $noNeedPermission = ['index','batchAdd',"getExportRecharge","export","batchPass",'batchRefuse'];


        protected bool|string|int $dataLimit = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Recharge();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($this->withJoinTable,'accountrequestProposal');

        $indexField = '';

        array_push($this->withJoinTable,'companyAccount');
        array_push($where,['companyAccount.company_id','=',$this->auth->company_id]);
        $showAudit = 1;
        if($this->auth->type == 4)
        {            
            // $indexField = 'id,status,create_time,update_time,accountrequestProposal.serial_name,accountrequestProposal.account_id';
            array_push($where,['companyAccount.team_id','=',$this->auth->team_id]);
            array_push($where,['recharge.team_id','=',$this->auth->team_id]);
            $showAudit = 0;
        }

        $res = $this->model
            ->field($indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $dataList = $res->toArray()['data'];
        foreach($dataList as &$v){
            $v['show_audit'] = $showAudit;
        }

        $res->visible(['accountrequestProposal' => ['serial_name'],'companyAccount'=>['id']]);

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function batchAdd(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));

            $list = $data['list'];

            if(empty($list)) $this->error('参数错误');
            if(count($list) > 30) $this->error('批量添加不能超过30条');

            $lock = new \app\services\RedisLock();

            $subManagement = false;  //是否二级管理
            if($this->auth->type != 4) $subManagement =true;

            $team_id = $this->auth->team_id;
            //团队是否审核
            $isAudit = false;
            if($this->auth->type == 4 && !empty($team_id))
            {
                $teamAudit = DB::table('ba_team')->where('id',$team_id)->value('is_audit');
                if($teamAudit==1)
                {
                    $isAudit = true;
                }
            }        

            $errorList = [];
            foreach($list as $v)
            {
                $accountId = $v['account_id'];
                $amount = $v['amount'];
                $type = $v['type'];
                $lockValue = uniqid();
                $expire = 180;
                $data = [];

                $acquired = $lock->acquire($accountId, $lockValue, $expire);
                if(!$acquired) $this->error($accountId.":该需求被锁定，处理中！");
                
                DB::startTrans();
                try {

                    $where = [['account.account_id','IN',$accountId],['account.status','=',4]];
                    $accountList = $this->getAccountPermission($where);
                    
                    if(empty($accountList)){
                        array_push($errorList,['account_id'=>$accountId,'msg'=>'未找到账户！']);
                        continue;
                    } 

                    $accountStatus = $accountList[0]['status'];
                    // $teamId = $accountList[0]['team_id'];     
                    
                    $nOTConsumptionStatus = config('basics.NOT_consumption_status');

                    if($accountStatus == 94) {
                        array_push($errorList,['account_id'=>$accountId,'msg'=>'该账户已进入系统回收池，当前不可操作，如需继续使用，请联系管理员。']);
                        continue;
                    }                          

                    if(in_array($accountStatus,$nOTConsumptionStatus) && !in_array($type,[3,4])){
                        array_push($errorList,['account_id'=>$accountId,'msg'=>'该账号暂停使用，请联系管理员！']);
                        continue;
                    }

                    $recharge = $this->model->where('account_id',$accountId)->whereIn('type',[3,4])->where('status',0)->find();
                    if(!empty($recharge)){
                        array_push($errorList,['account_id'=>$accountId,'msg'=>'已收到清零需求，请勿重复提交，如需加急，请联系客服!']);
                        continue;
                    }

                    
                    if($acquired){
                        if($type == 1)
                        {
                            if($amount <= 0){
                                array_push($errorList,['account_id'=>$accountId,'msg'=>'充值金额不能小于零!']);
                                continue;
                            }
                            $usedMoney =  $this->teamUsedMoney($amount,$accountId);
                            if($usedMoney['code'] != 1){
                                array_push($errorList,['account_id'=>$accountId,'msg'=>$usedMoney['msg']]);
                                continue;
                            }
                        }else if($type == 2)
                        {

                        }else if(in_array($type,[3,4]))
                        {
                            $recharge = $this->model->where('account_id',$accountId)->where('status',1)->whereIn('type',[3,4])->order('id','desc')->find();

                            if(!empty($recharge['id'])){
                                $where = [
                                    ['account_id','=',$accountId],
                                    ['type','=',1],
                                    ['id','>',$recharge['id']],
                                    ['status','=',1]
                                ];
            
                                $recharge2 = $this->model->where($where)->find();
                                if(!empty($recharge) && empty($recharge2)) throw new \Exception("账号已经完成了清零请求,不可以在提交清零与扣款!");
                            }

                            $cards = DB::table('ba_accountrequest_proposal')
                            ->field('cards_info.id,cards_info.card_status,cards_info.card_id,cards_info.account_id,accountrequest_proposal.is_cards')
                            ->alias('accountrequest_proposal')
                            ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                            ->where('accountrequest_proposal.account_id',$accountId)
                            ->find();
                            if(!empty($cards) && $cards['is_cards'] != 2 && $cards['card_status'] == 'normal') {
                                $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                                if($resultCards['code'] != 1) array_push($errorList,['account_id'=>$accountId,'msg'=>$resultCards['msg']]);// throw new \Exception($resultCards['msg']);
                                if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                                (new \app\admin\services\card\Cards())->allCardFreeze($accountId);
                            }
                        }
                    }else{
                        throw new \Exception('该账户暂时被锁定，请稍后再试！');
                    }
               
                    if($subManagement == false)if($isAudit == true) $data['audit_status'] = 1;//非二级//需审核
                    $data['create_time'] = time();
                    $data['type'] = $type;
                    $data['account_id'] = $accountId;
                    $data['admin_id'] = $this->auth->id;                    
                    $data['team_id'] = $this->auth->team_id??'';                    
                    $data['add_operate_user'] = $this->auth->id;
                    if(in_array($type,[3,4]))$data['number'] = 0;
                    else $data['number'] = $amount;
                    $rechargeId = DB::table('ba_recharge')->insertGetId($data);


                    if ($rechargeId) {
                        if($subManagement == true || $isAudit == false) $this->rechargeJob2($rechargeId,$type); //是二级或者不需要审核
                    }

                    $lock->release($accountId, $lockValue);
                    DB::commit();
                }catch(\Exception $e) {
                    DB::rollback();
                    $errorList[] = ['account_id'=>$accountId,'msg'=>$e->getMessage()];
                }finally {
                    // $lock->release($accountId, $lockValue);
                }        

            }
            $this->success(__('Added successfully'),['error_list'=>$errorList]);
        }

        $this->error(__('Parameter error'));
    }

    public function rechargeJob2($id,$type)
    {
        if(in_array($type,[3,4])){
            $this->addDeleteJob($id);
        }else if(in_array($type,[1])){
            $this->addUpJob($id);
        }else if(in_array($type,[2])){
            $this->addDeductionsJob($id);
        }
        return true;
    }

    public function addUpJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendUp';
        $jobQueueName = 'AccountSpendUp';
        Queue::later(1, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }

    public function addDeleteJob($id)
    {
        $deleteTime = DB::table('ba_company')->where('id',$this->auth->company_id)->value('delete_time');
        $jobHandlerClassName = 'app\job\AccountSpendDelete';
        $jobQueueName = 'AccountSpendDelete';
        Queue::later($deleteTime, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }

    public function addDeductionsJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendDeductions';
        $jobQueueName = 'AccountSpendDeductions';
        Queue::later(1, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }

    public function export()
    {
        $ids = $this->request->get('ids');
        $where = [];
        set_time_limit(300);
        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_recharge'.'_'.$this->auth->id;
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['account.company_id','=',$this->auth->company_id]);
        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);

        $query = DB::table('ba_recharge')
        ->field('recharge.id,accountrequestProposal.bm,accountrequestProposal.serial_name,accountrequestProposal.account_id,recharge.type,recharge.number,recharge.status,recharge.create_time,recharge.update_time')
        ->alias('recharge')
        ->leftJoin('ba_accountrequest_proposal accountrequestProposal','accountrequestProposal.account_id=recharge.account_id')
        ->leftJoin('ba_account account','account.account_id=recharge.account_id')
        ->where($where);

        $total = $query->count();

        $type = [1=>'充值',2=>'扣款',3=>'封户清零',4=>'活跃清零'];
        $status = [0=>'待处理',1=>'成功',2=>'失败'];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'ID',
            // 'Bm',
            '账户名称',
            '账户ID',
            '类型',
            '金额',
            '状态',
            '创建时间',
            '修改时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);


        $name = $folders['name'].'.xlsx';
        $excel->fileName($folders['name'].'.xlsx', 'sheet1');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->order('id','desc')->limit($offset, $batchSize)->select()->toArray();
           
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['id'],
                    // $v['bm'],
                    $v['serial_name'],
                    $v['account_id'],
                    $type[$v['type']]??'',
                    $v['number'],
                    $status[$v['status']]??'',
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):'',
                    $v['update_time']?date('Y-m-d H:i',$v['update_time']):'',
                ];  
                $processedCount++;
            }
            $excel->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportRecharge()
    {
        $progress = Cache::store('redis')->get('export_recharge'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    public function batchPass(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));
    
            $ids = $data['ids'];
            $auditStatus = $data['audit_status'];
            if(empty($ids)) $this->error('参数错误');
            if(empty($auditStatus)) $this->error('参数错误');
            if(count($ids) > 100) $this->error('批量不能超过100条');

            foreach($ids as $id){
                $result = DB::table('ba_recharge')->where('audit_status',1)->where('id',$id)->update(['audit_status'=>$auditStatus]);
                if($result){
                   $type = DB::table('ba_recharge')->where('id',$id)->value('type');
                   $this->rechargeJob2($id,$type);
                }
            }

            if ($result !== false) {
                 $this->success(__('Update successful'));
            } else {
                 $this->error(__('No rows updated'));
            }
        }
        $this->error(__('Parameter error'));
    }

    public function batchRefuse(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));
    
            $ids = $data['ids'];
            $auditStatus = $data['audit_status'];
            if(empty($ids)) $this->error('参数错误');
            if(empty($auditStatus)) $this->error('参数错误');
            if(count($ids) > 100) $this->error('批量不能超过100条');

            $result = DB::table('ba_recharge')->where('audit_status',1)->whereIn('id',$ids)->update(['audit_status'=>$auditStatus]);

            if ($result !== false) {
                 $this->success(__('Update successful'));
            } else {
                 $this->error(__('No rows updated'));
            }
        }
        $this->error(__('Parameter error'));
    }



    
}