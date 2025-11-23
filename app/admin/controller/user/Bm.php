<?php

namespace app\admin\controller\user;

use Throwable;
use app\admin\model\User;
use app\admin\model\UserMoneyLog;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;

class Bm extends Backend
{
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['create_time'];

    protected string|array $quickSearchField = ['user.username', 'user.nickname'];

    protected array $noNeedPermission = ['index','unbinding','getBmList','batchUnbindAdd','batchAdd','edit','progressList',"export","getExportBm","batchPass","batchRefuse"];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Bm();
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
        
        // $indexField = 'bm.id,bm.bm,bm.bm_type,bm.status,bm.dispose_type,bm.choice_jurisdiction,bm.comment,bm.create_time,bm.update_time,accountrequestProposal.serial_name,accountrequestProposal.account_id';
        $showAudit = 1;
        if($this->auth->type == 4) {
            array_push($where,['companyAccount.team_id','=',$this->auth->team_id]);
            array_push($where,['bm.team_id','=',$this->auth->team_id]);
            $showAudit = 0;
        }

        

        $res = $this->model
            ->field($indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
            // ->fetchSql()->find();
        $dataList = $res->toArray()['data'];
        foreach($dataList as &$v){
            $v['show_audit'] = $showAudit;
        }

        // dd($res);
        $res->visible(['accountrequestProposal' => ['serial_name'],'companyAccount'=>['id']]);
        // dd($res);

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function unbinding(): void
    {         
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {

                $accountId = $data['account_id']??'';
                $checkList = $data['checkList']??[];

                $where = [];
                array_push($where,['account.status','=',4]);
                array_push($where,['account.account_id','=',$accountId]);
                $table = DB::table('ba_account')->alias('account')->field('account.account_id,accountrequest_proposal.status')
                ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id');
                
                array_push($where,['account.company_id','=',$this->auth->company_id]);
                if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
                

                $account = $table->where($where)->find();
                
                if(empty($account)) throw new \Exception('未找到账户');

                $notConsumptionStatus   = config('basics.NOT_consumption_status');
                if(in_array($account['status'],$notConsumptionStatus)) throw new \Exception("该账户已经终止使用，不可操作，请联系管理员！");
                

                //-----------------------------------------
                $bmArr = DB::table('ba_bm')
                ->field('bm,bm_type,choice_jurisdiction')
                ->where('account_id',$accountId)
                ->whereIn('demand_type',[1,4])
                ->whereIn('bm',$checkList)
                ->where('dispose_type',1)
                ->where('new_status',1)
                ->group('account_id,bm')
                ->select()->toArray();

                if(empty($bmArr)) throw new \Exception('没有绑定完成的！');
                
                $bmList = DB::table('ba_bm')->where('account_id',$accountId)->whereIn('bm',$checkList)
                ->where(function ($quant){
                    $quant->whereOr([['status','=',0],['status','=',1]]);
                })
                ->where([['dispose_type','=',0]])->column('bm');
                $error = [];

                foreach($bmArr as $v)
                {
                    if(in_array($v['bm'],$bmList)){
                        array_push($error,[$v['bm'],'该BM需求在处理中,不需要重复提交!!!']);
                        continue;
                    }

                    $dataList[] = [
                        'demand_type'=>2,
                        'account_id'=>$accountId,
                        'bm'=>$v['bm'],
                        'bm_type'=>$v['bm_type'],
                        'choice_jurisdiction'=>$v['choice_jurisdiction'],
                        'admin_id'=>$this->auth->id,
                        'add_operate_user'=>$this->auth->id,
                        'create_time'=>time()
                    ];
                }
                if(!empty($dataList)) $result = $this->model->insertAll($dataList);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'),['error'=>$error]);
            } else {
                $this->error(__('No rows were added'),['error'=>$error]);
            }
        }
        $this->error(__('Parameter error'));
    }

    public function getBmList()
    {
        $bmList = [];
        try {
            $accountId = $this->request->get('account_id');
            
            $where = [];
            array_push($where,['account.status','=',4]);
            array_push($where,['account.account_id','=',$accountId]);
            $table = DB::table('ba_account')->alias('account')->field('account.account_id,accountrequest_proposal.status')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id');

            array_push($where,['account.company_id','=',$this->auth->company_id]);
            if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
            
            $account = $table->where($where)->find();
            
            if(empty($account)) throw new \Exception('未找到账户');
            $notConsumptionStatus   = config('basics.NOT_consumption_status');
            if(in_array($account['status'],$notConsumptionStatus)) throw new \Exception("该账户已经终止使用，不可操作，请联系管理员！");


            $result =  DB::table('ba_bm')
            ->where('account_id',$accountId)
            ->whereIn('demand_type',[1,4])
            ->where('dispose_type',1)
            ->where('new_status',1)
            ->group('account_id,bm')
            ->select()->toArray();

            $bmList = array_column($result,'bm');
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
        $this->success('',['bmList'=>$bmList]);
    }

    public function batchUnbindAdd(): void
    {         
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            $this->model->startTrans();
            try {

                $accountIds = $data['account_list']??[];
                $bmList = $data['bm_list']??[];

                if(empty($accountIds)) throw new \Exception('请选择要操作的账户');

                $where = [['account.account_id','IN',$accountIds],['account.status','=',4]];
                $accountList = $this->getAccountPermission($where);
                
                if(empty($accountList)) throw new \Exception('未找到账户');

                $accountIds = array_column($accountList,'account_id');

                $bmArr = DB::table('ba_bm')
                ->field('account_id,bm,bm_type,choice_jurisdiction')
                ->whereIn('account_id',$accountIds)
                ->whereIn('bm', $bmList)
                ->whereIn('demand_type',[1,4])
                ->where('dispose_type',1)
                ->where('new_status',1)
                ->group('account_id,bm')
                ->select()->toArray();


                if(empty($bmArr)) throw new \Exception('未找到绑定完成的需求请确定或联系客服！');
                $error = [];

                foreach($bmArr as $v)
                {

                    $bm = DB::table('ba_bm')->where('account_id',$v['account_id'])->where('bm',$v['bm'])
                    ->where(function ($quant){
                        $quant->whereOr([['status','=',0],['status','=',1]]);
                    })
                    ->where([['dispose_type','=',0]])->value('bm');

                    if(!empty($bm)) {
                        $error[] = ['bm'=>'(账户)'.$v['account_id'].' - (BM)'.$v['bm'],'msg'=>'该BM已经提交过需求，不需要重复提交!'];
                        continue;
                    }

                    $dataList[] = [
                        'demand_type'=>2,
                        'account_id'=>$v['account_id'],
                        'bm'=>$v['bm'],
                        'bm_type'=>$v['bm_type'],
                        'choice_jurisdiction'=>$v['choice_jurisdiction'],
                        'admin_id'=>$this->auth->id,
                        'add_operate_user'=>$this->auth->id,
                        'create_time'=>time()
                    ];
                }
                if(!empty($dataList)) $result = $this->model->insertAll($dataList);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'),['errorList'=>$error]);
            } else {
                $this->error(__('No rows were added'),['errorList'=>$error]);
            }
        }
        $this->error(__('Parameter error'));
    }

    public function batchAdd(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));

            $accountList = $data['account_list'];
            $bmList = $data['bm_list'];
            $bmList = array_column($bmList,"choice_jurisdiction",'bm');

            if(empty($accountList) || empty(array_unique(array_keys($bmList)))) $this->error('参数错误');
            if(count($accountList) > 100) $this->error('批量添加不能超过100条');

            $subManagement = false;  //是否二级管理
            if($this->auth->type != 4) $subManagement =true;

            $team_id = $this->auth->team_id;
             //团队是否审核
             $isAudit = false;
             if($this->auth->type == 4) if(!empty($team_id))
             {
                 $teamAudit = DB::table('ba_team')->where('id',$team_id)->value('is_audit');
                 if($teamAudit==1)
                 {
                     $isAudit = true;
                 }
             }


 /**
             * 查账户是不是该团队的（后期加直接跳过）
             * 查账户是不是被禁用
             * 绑定还是解绑
             * 判断格式是否正确
             * 
             */
            
            try {
                $errorList = [];
                $dataList  = [];

                $where = [];
                $where[] = ['account.status','=',4];
                $table = DB::table('ba_account')
                ->alias('account')
                ->field('accountrequest_proposal.account_id,accountrequest_proposal.status')
                ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
                ->whereIn('account.account_id',$accountList);

                array_push($where,['account.company_id','=',$this->auth->company_id]);
                if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
                

                $accountList = $table->where($where)->select()->toArray();

                if(empty($accountList))  throw new \Exception('未找到账户');

                $nOTConsumptionStatus = config('basics.NOT_consumption_status');

                foreach($accountList as $k => $v){
                    if(in_array($v['status'],$nOTConsumptionStatus))
                    {
                        $errorList[] = ['bm'=>'(账户ID)'.$v['account_id'],'msg'=>'该账户已经终止使用，不可操作，请联系管理员！'];
                        unset($accountListC[$k]);
                    }
                }

                $accountListC = array_column($accountList,'account_id');

                $bmListC = [];
                foreach($bmList as $v => $vv){
                    if(filter_var($v, FILTER_VALIDATE_EMAIL)){                    
                        $isEmail = (new \app\services\Basics())->isEmail($v);
                        if($isEmail['code'] != 1) {
                            $errorList[] = ['bm'=>$v,'msg'=>$isEmail['msg']];
                            continue;
                        }
                        $bmListC[$v] = [
                            'bm_type'=>2,
                            'choice_jurisdiction'=>$vv,
                        ];
                    }else if (preg_match('/^\d+$/', $v)) {
                        $bmListC[$v] = [
                            'bm_type'=>1,
                            'choice_jurisdiction'=>$vv,
                        ];
                    }else{
                        $errorList[] = ['bm'=>$v,'msg'=>'BM格式错误,请填写正确的BM或邮箱!'];
                    }
                }

                
                foreach($accountListC as $v)
                {
                    foreach($bmListC as $k2 => $v2)
                    {
                        $bm = Db::table('ba_bm')
                            ->where('account_id', $v)
                            ->where('bm',  $k2)
                            ->whereIn('demand_type', [1,4])
                            ->where('new_status', 1) 
                            ->where(function($query) {
                                $query->where(function($q) {
                                    $q->where('status', '<>', '2')
                                    ->where('dispose_type', '<>', '2');
                                })->whereOr(function($q) {
                                    $q->where('dispose_type', '1');
                                });
                            })
                        ->value('bm');

                        if(!empty($bm)) {
                            $errorList[] = ['bm'=>'(账户)'.$v.' - (BM)'.$k2,'msg'=>'该BM已经提交过需求，不需要重复提交!'];
                            continue;
                        }
                        $auditStatus = 2;
                        if($subManagement == false)if($isAudit == true) $auditStatus = 1;//非二级//需审核
                    //  dd($this->auth->team_id);
                        $dataList[] = [
                            'demand_type'=>1,
                            'account_id'=>$v,
                            'bm'=>$k2,
                            'bm_type'=>$v2['bm_type'],
                            'choice_jurisdiction'=>$v2['choice_jurisdiction'],
                            'account_name'=>'',
                            'admin_id'=>$this->auth->id,
                            'add_operate_user'=>$this->auth->id,
                            'audit_status'=>$auditStatus,
                            'team_id'=>$this->auth->team_id??'',
                            'create_time'=>time()
                        ]; 
                    }      
                }
                DB::table('ba_bm')->insertAll($dataList);
            } catch (\Exception $th) {
                $this->error($th->getMessage(),$th->getLine());
            }
            $this->success(__('Added successfully'),['error_list'=>$errorList]);
        }

        $this->error(__('Parameter error'));
    }

    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if($row['status'] != 0 || $row['dispose_type'] != 0){
            $this->error('该状态不可编辑');
        }

        $getAccountPermission = $this->getAccountPermission([['account.account_id','=',$row['account_id']]],'account.account_id',['account']);
        if (empty($getAccountPermission)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                
                if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['bm']) > 0) throw new \Exception("BM不能包含中文");

                if($data['demand_type'] == 1 && $data['bm_type'] == 1 && preg_match('/[a-zA-Z]/', $data['bm'])) throw new \Exception("BM与选择的类型不匹配,请重新选择！");
                if($data['demand_type'] == 1 && $data['bm_type'] == 2 && !filter_var($data['bm'], FILTER_VALIDATE_EMAIL)) throw new \Exception("BM与选择的类型不匹配,请重新选择！");

                if($data['demand_type'] == 2 && !is_numeric($data['bm']) && !filter_var($data['bm'], FILTER_VALIDATE_EMAIL))throw new \Exception("提交格式错误,请重新填写！");

                if($row['demand_type'] == 4 || $data['demand_type'] == 4) unset($data['demand_type']);

                if($data['demand_type'] == 1 && $data['bm_type'] == 2)
                {
                    $isEmail = (new \app\services\Basics())->isEmail($data['bm']);
                    if($isEmail['code'] != 1) throw new \Exception($isEmail['msg']);
                }

                unset($data['bm_type']);
                unset($data['demand_type']);
                    
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }


    public function progressList()
    {
        $id = $this->request->get('id');

        $bm = DB::table('ba_bm')->where('id',$id)->field('account_id')->find();
        $getAccountPermission = $this->getAccountPermission([['account.account_id','=',$bm['account_id']??'']],'account.account_id',['account']);
        if (empty($getAccountPermission)) {
            $this->error(__('You have no permission'));
        }

        try {
            $result = DB::table('ba_bm_progress')->where('bm_id',$id)->order('id','desc')->select()->toArray();

        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
        return $this->success('',['list'=>$result]);
    }


    public function export()
    {
        $ids = $this->request->get('ids');
        $where = [];
        set_time_limit(300);
        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_bm'.'_'.$this->auth->id;
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($this->withJoinTable,'account');

        array_push($where,['account.company_id','=',$this->auth->company_id]);
        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);

        $query = DB::table('ba_bm')
        ->alias('bm')
        ->field('bm.id,accountrequestProposal.bm accountrequestProposal_bm,accountrequestProposal.serial_name,accountrequestProposal.account_id,bm.bm,bm.demand_type,bm.status,bm.dispose_type,bm.choice_jurisdiction,bm.comment,bm.create_time')
        ->leftJoin('ba_accountrequest_proposal accountrequestProposal','accountrequestProposal.account_id=bm.account_id')
        ->leftJoin('ba_account account','account.account_id=bm.account_id')
        ->where($where);

        $total = $query->count();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账户名称',
            '账户ID',
            '绑定',
            '需求类型',
            '处理',
            '权限',
            '备注',
            '创建时间',
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);
        
        $choiceJurisdictionList = [1=>'广告',2=>'财务'];
        $demandTypeList = [1=>"绑定",2=>"解绑",3=>"全部解绑",4=>"开户绑定"];
        $statusName = '';


        $name = $folders['name'].'.xlsx';
        $excel->fileName($folders['name'].'.xlsx', 'sheet1');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->order('id','desc')->limit($offset, $batchSize)->select()->toArray();

            $dataList=[];
            foreach($data as $v){

                if($v['dispose_type'] != 0){
                    if($v['dispose_type'] ==1) $statusName = '处理完成';
                    else $statusName = '处理异常';
                }else{
                    if($v['status'] ==0) $statusName = '待审核';
                    else if($v['status'] ==1) $statusName = '审核通过';
                    else $statusName = '审核拒绝';
                }
                
                $dataList[] = [
                    $v['serial_name'],
                    $v['account_id'],
                    $v['bm'],
                    $demandTypeList[$v['demand_type']]??'',
                    $statusName,
                    $choiceJurisdictionList[$v['choice_jurisdiction']]??'',
                    $v['comment'],
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):''
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

    public function getExportBm()
    {
        $progress = Cache::store('redis')->get('export_bm'.'_'.$this->auth->id, 0);
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
                $result = DB::table('ba_bm')->where('audit_status',1)->where('id',$id)->update(['audit_status'=>$auditStatus]);
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

            $result = DB::table('ba_bm')->where('audit_status',1)->whereIn('id',$ids)->update(['audit_status'=>$auditStatus]);

            if ($result !== false) {
                 $this->success(__('Update successful'));
            } else {
                 $this->error(__('No rows updated'));
            }
        }
        $this->error(__('Parameter error'));
    }
    
}