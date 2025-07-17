<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use think\facade\Db;
use Throwable;
use app\common\service\QYWXService;

/**
 * BM需求
 */
class Bm extends Backend
{
    /**
     * Bm模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\Bm
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_name', 'status', 'dispose_type', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    protected array $withJoinTable = ['admin'];

    protected array $noNeedPermission = ['batchAdd','disposeStatus','index','getBmList','getBmAnnouncement','progressList','progress','disposeAll'];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Bm();
    }


    public function index2(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($this->withJoinTable,'accountrequestProposal');
        // array_push($this->withJoinTable,'accountrequestProposalAdmin');

        foreach($where as $k => &$v){
            if($v[0] == 'bm.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
            }
        }

        $disposeType = $this->request->get('dispose_type');

        $getGroups = $this->auth->getGroups()[0];
        if($getGroups['group_id'] == 5){
            foreach($where as $k => $v){
                if($v[0] == 'bm.admin_id'){
                    unset($where[$k]);
                }
            }
            //array_push($this->withJoinTable,'accountrequestProposal');
            array_push($where,['accountrequestProposal.admin_id','=',$this->auth->id]);            
        }
        if($disposeType == 1){
            array_push($where,['bm.dispose_type','in',[1,2]]);
        }elseif($disposeType == 2){
            array_push($where,['bm.status','=',1]);
            array_push($where,['bm.dispose_type','=',0]);
        }else{
            // array_push($where,['bm.status','=',1]);
            // array_push($where,['bm.dispose_type','=',0]);
        }
       
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        array_push($this->withJoinTable,'accountrequestProposal');
        //array_push($this->withJoinTable,'accountrequestProposalAdmin');

        foreach($where as $k => &$v){
            if($v[0] == 'bm.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
                continue;
            }
            if($v[0] == 'bm.account_requestProposal_admin'){
                $v[0] = 'accountrequestProposal.admin_id';
                continue;
            }

        }
        $disposeType = $this->request->get('dispose_type');

        $getGroups = $this->auth->getGroups()[0];
        if($getGroups['group_id'] == 5){
            foreach($where as $k => $v){
                if($v[0] == 'bm.admin_id'){
                    unset($where[$k]);
                }
            }
            //array_push($this->withJoinTable,'accountrequestProposal');
            array_push($where,['accountrequestProposal.admin_id','=',$this->auth->id]);            
        }
        if($disposeType == 1){
            array_push($where,['bm.dispose_type','in',[1,2]]);
        }elseif($disposeType == 2){
            array_push($where,['bm.status','=',1]);
            array_push($where,['bm.dispose_type','=',0]);
        }elseif($disposeType == 3){
            array_push($where,['bm.status','=',0]);
            array_push($where,['bm.demand_type','<>',4]);
            $bmShieldList = DB::table('ba_bm_shield')->column('bm');
            if(!empty($bmShieldList)) array_push($where,['accountrequestProposal.bm','not in',$bmShieldList]);
        }elseif($disposeType == 4){
            array_push($where,['bm.status','=',0]);
            array_push($where,['bm.demand_type','=',4]);
        }
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $dataList = $res->toArray()['data'];
        $adminNameArr = DB::table('ba_admin')->column('nickname','id');
        //dd($dataList);
        if($dataList){
            
            $adminIds = [];
            foreach($dataList as $v){
                if(!empty($v['accountrequestProposal']['admin_id'])) $adminIds[] = $v['accountrequestProposal']['admin_id'];
            }        
            $admin = DB::table('ba_admin')->whereIn('id',$adminIds)->select()->toArray();
            $bmBlackList = DB::table('ba_bm_blacklist')->select()->column('bm');
            $adminList = [];
            foreach($admin as $v){
                $adminList[$v['id']] = $v['nickname'];
            }
           
            
            foreach($dataList as &$v){
                $v['admin'] = [
                    // 'username'=>$adminNameArr[$v['admin_id']]??"",
                    'nickname'=>$adminNameArr[$v['admin_id']]??""
                ];
                $v['account_requestProposal_admin'] = $adminList[$v['accountrequestProposal']['admin_id']??0]??'';
                if(in_array($v['bm'],$bmBlackList)) $v['blacklist'] = '黑名单';
                else $v['blacklist'] = '';
            }
        }
            

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }



    public function add(): void
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
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }

                $demandType = $data['demand_type']??'';
                $accountId = $data['account_id']??'';
                $bm = $data['bm']??'';
                $bmType = $data['bm_type']??1;
                $checkList = $data['checkList']??[];
             
                if($bm) array_push($checkList,$bm);

                $checkList = array_unique($checkList);

                $account = Db::table('ba_account')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->where('status',4)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID完成状态!"); //未完成开户绑定拦截
// //-----------------------------------------
//                 $result =  DB::table('ba_bm')
//                 ->where('account_id',$accountId)
//                 ->whereIn('demand_type',[1,4])
//                 ->whereIn('bm',$checkList)
//                 ->where('dispose_type',1)
//                 ->where('new_status',1)
//                 ->group('account_id,bm')
//                 ->select()->toArray();
//                 if(empty($result)){
//                     //不能解绑
//                      array_column($result,'bm');
//                 }else{
//                     //可以解绑 但是要查是否已经有解绑记录
//                     $result =  DB::table('ba_bm')
//                     ->where('account_id',$accountId)
//                     ->whereIn('demand_type',[1,4])
//                     ->whereIn('bm',array_column($result,'bm'))
//                     ->where('new_status',2);
//                 }
//                 dd(array_column($result,'bm','bm'));
// //------------------------------------------
                $notConsumptionStatus = config('basics.NOT_consumption_status');
                $accountrequestProposal = Db::table('ba_accountrequest_proposal')->where('account_id',$accountId)->value('status');
                if(empty($accountrequestProposal) || in_array($accountrequestProposal,$notConsumptionStatus)) throw new \Exception("未找到账户或该账户已经终止使用，不可操作，请联系管理员！");

                if(empty($checkList) && $demandType != 3) throw new \Exception("请填写或需要操作的BM");

                //TODO... 只要是未完成，注意失败的，可以不可以在提交
                $bmList = DB::table('ba_bm')->where('account_id',$accountId)->whereIn('bm',$checkList)
                ->where(function ($quant){
                    $quant->whereOr([['status','=',0],['status','=',1]]);
                })
                ->where([['dispose_type','=',0]])->column('bm');
                $error = [];

                
                $dataList = [];
                if(!empty($checkList)){
                    foreach($checkList as $v){
                        if(in_array($v,$bmList)){
                            array_push($error,[$v,'该BM需求在处理中,不需要重复提交!!!']);
                            continue;
                        } 
                        if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $v) > 0) throw new \Exception("BM不能包含中文");

                        if($demandType == 1 && $bmType == 1 && preg_match('/[a-zA-Z]/', $v)) throw new \Exception("BM与选择的类型不匹配,请重新选择！");
                        if($demandType == 1 && $bmType == 2 && !filter_var($v, FILTER_VALIDATE_EMAIL)) throw new \Exception("BM与选择的类型不匹配,请重新选择！");

                        if($demandType == 2 && !is_numeric($v) && !filter_var($v, FILTER_VALIDATE_EMAIL))throw new \Exception("提交格式错误,请重新填写！");
                        //if (filter_var($v, FILTER_VALIDATE_EMAIL) && $demandType == 1 && $bmType != 2) throw new \Exception("BM与选择的类型不匹配,请重新选择！");
                        // dd($checkList,!filter_var($v, FILTER_VALIDATE_EMAIL));
                        $dataList[] = [
                            'demand_type'=>$demandType,
                            'account_id'=>$accountId,
                            'bm'=>$v,
                            'bm_type'=>$bmType,
                            'account_name'=>$account['name'],
                            'admin_id'=>$this->auth->id,
                            'create_time'=>time()
                        ];
                    }
                }else{
                    $dataList[] = [
                        'demand_type'=>$demandType,
                        'account_id'=>$accountId,
                        'bm'=>'',
                        'bm_type'=>$bmType,
                        'account_name'=>$account['name'],
                        'admin_id'=>$this->auth->id,
                        'create_time'=>time()
                    ];
                }
                //$data['account_name'] = $account['name'];
                //$data['admin_id'] = $this->auth->id;

                if(env('IS_ENV',false)) (new QYWXService())->bmSend(['account_id'=>$accountId],$demandType);
                $result = $this->model->insertAll($dataList);

                //$result = $this->model->save($data);
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


    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if($row['status'] != 0){
            $this->error('该状态不可编辑');
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
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
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }

                $accountId = $data['account_id']??'';
                $account = Db::table('ba_account')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID");
                if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['bm']) > 0) throw new \Exception("BM不能包含中文");

                if($data['demand_type'] == 1 && $data['bm_type'] == 1 && preg_match('/[a-zA-Z]/', $data['bm'])) throw new \Exception("BM与选择的类型不匹配,请重新选择！");
                if($data['demand_type'] == 1 && $data['bm_type'] == 2 && !filter_var($data['bm'], FILTER_VALIDATE_EMAIL)) throw new \Exception("BM与选择的类型不匹配,请重新选择！");

                if($data['demand_type'] == 2 && !is_numeric($data['bm']) && !filter_var($data['bm'], FILTER_VALIDATE_EMAIL))throw new \Exception("提交格式错误,请重新填写！");

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

    public function audit(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $comment = $data['comment']??'';

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                $bmTemplate = new \app\admin\model\demand\BmTemplate();
                $servicesBasics = new \app\services\Basics();

                $progressData = [];
                $bmData = [];
                $commentValue = '';
                $adminId = $this->auth->id;
                foreach($ids as $v){

                    $getTemplateValue = $bmTemplate->getTemplateValue($comment,$v['account_id']);

                    switch ($status) {
                        case '1':
                            $disposeStatus  = 2;
                            $commentValue = '已提交:'.$getTemplateValue;
                            break;
                        case '2':
                            $disposeStatus = 3;
                            $commentValue = '提交异常:'.$getTemplateValue;
                            break;
                        case '3':
                            $disposeStatus = 1;
                            $commentValue = '处理完成:'.$getTemplateValue;
                            break;
                        default:
                            break;
                    }

                    if($v['demand_type'] == 2 && $disposeStatus == 1) $this->model->where('account_id',$v['account_id'])->where('bm',$v['bm'])->update(['new_status'=>2]);
                    
                    if($v['demand_type'] == 4 && in_array($disposeStatus,[1,3])){
                        $v['comment'] = $comment;
                        $this->bmOperation($v);
                    }

                    $progressData[] = [
                        'bm_id'=>$v['id'],
                        'comment'=>$commentValue,
                        'create_time'=>time()
                    ];

                    
                    if($status == 3){
                        $bmData[] = ['operate_admin_id'=>$adminId,'id'=>$v['id'],'status'=>1,'dispose_type'=>1,'comment'=>$getTemplateValue,'update_time'=>time()];
                    }else{
                        $bmData[] = ['operate_admin_id'=>$adminId,'id'=>$v['id'],'status'=>$status,'comment'=>$getTemplateValue,'update_time'=>time()];
                    }                
                }

                $servicesBasics->dbBatchUpdate('ba_bm',$bmData, 'id');

                // $bmData = [];
                // if($status == 3){
                //     $bmData = ['status'=>1,'dispose_type'=>1,'comment'=>$comment,'update_time'=>time()];
                // }else{
                //     $bmData = ['status'=>$status,'comment'=>$comment,'update_time'=>time()];
                // }
                //$result = $this->model->whereIn('id',array_column($ids,'id'))->update($bmData);
                
                DB::table('ba_bm_progress')->insertAll($progressData);

                $result = true;
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
    }

    public function getBmList()
    {
        $bmList = [];
        try {
            $accountId = $this->request->get('account_id');
            $account = Db::table('ba_account')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->find();
            if(empty($account)) throw new \Exception("未找到该账户ID");

            $result =  DB::table('ba_bm')
            ->where('account_id',$accountId)
            ->whereIn('demand_type',[1,4])
            ->where('dispose_type',1)
            ->where('new_status',1)
            ->group('account_id,bm')
            ->select()->toArray();

            // $result = Db::table('ba_bm')
            // ->alias('t1')
            // ->where('t1.account_id',$accountId)
            // ->join('(SELECT account_id, bm, MAX(id) AS max_id FROM ba_bm GROUP BY account_id, bm) t2', 't1.id = t2.max_id')
            // ->order('t1.id', 'desc')
            // ->having('demand_type = 1')
            // ->select()->toArray();

            $bmList = array_column($result,'bm');
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
        }
        $this->success('',['bmList'=>$bmList]);
    }

    public function disposeStatus(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $comment = $this->request->param('comment',null,null)??'';

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray(); 

                $bmTemplate = new \app\admin\model\demand\BmTemplate();
                $servicesBasics = new \app\services\Basics();

                $accountIds = [];
                $progressData = [];
                $commentValue = '';
                $bmData = [];
                $adminId = $this->auth->id;
                foreach($ids as $v){
                    $getTemplateValue = $bmTemplate->getTemplateValue($comment,$v['account_id']);

                    $accountIds[] = $v['account_id'];
                    if($v['demand_type'] == 2 && $status == 1) $this->model->where('account_id',$v['account_id'])->where('bm',$v['bm'])->update(['new_status'=>2]);
                    if($v['demand_type'] == 4 && in_array($status,[1,2])){
                        $v['comment'] = $comment;
                        $this->bmOperation($v);
                    } 
                    
                    if($status == 1) $commentValue = '处理完成:'.$getTemplateValue;
                    else if($status == 2) $commentValue = '处理异常:'.$getTemplateValue;

                    $progressData[] = [
                        'bm_id'=>$v['id'],
                        'comment'=>$commentValue,
                        'create_time'=>time()
                    ];

                    $bmData[] = [
                        'operate_admin_id'=>$adminId,'id'=>$v['id'],'dispose_type'=>$status,'comment'=>$getTemplateValue,'update_time'=>time()
                    ];
                }
                
                //$this->model->whereIn('id',array_column($ids,'id'))->update(['dispose_type'=>$status,'comment'=>$getTemplateValue,'update_time'=>time()]);
                $servicesBasics->dbBatchUpdate('ba_bm',$bmData, 'id');

                DB::table('ba_bm_progress')->insertAll($progressData);

                $result = true;
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
    }

    public function disposeAll()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $disposeStatus = $data['dispose_status'];
                $comment = $this->request->param('comment',null,null)??'';

                $bmTemplate = new \app\admin\model\demand\BmTemplate();
                $servicesBasics = new \app\services\Basics();

                if($disposeStatus != 0){
                    if($status != 1) throw new \Exception("没有完成提交，不可以处理，请先提交完成！");
                    if($disposeStatus == 1){
                        $disposeStatus2 = 1;
                    }elseif($disposeStatus == 2){
                        $disposeStatus2 = 4;
                    }
                }elseif($status != 0){
                    if($status == 1){
                        $disposeStatus2 = 2;
                    }elseif($status == 2){
                        $disposeStatus2 = 3;
                    }
                }else{
                    $disposeStatus2 = 0;
                }
       
                $bmList = $this->model->whereIn('id',$ids)->field('id,demand_type,dispose_type,account_id,account_name,bm,admin_id')->select()->toArray(); 
                $accountTypeIds = [];
                $bmData = [];
                $adminId = $this->auth->id;
                foreach($bmList as $v){
                    //  if($v['dispose_type']==1)continue; //处理完成跳过不允许处理
                    $getTemplateValue = $bmTemplate->getTemplateValue($comment,$v['account_id']);
                    if($v['demand_type'] == 4 && (in_array($disposeStatus,[1,2]) || $status == 2)){
                        $v['comment'] = $comment;
                        $this->bmOperation($v);
                    }
                    $bmData[] = ['operate_admin_id'=>$adminId,'id'=>$v['id'],'status'=>$status,'dispose_type'=>$disposeStatus,'comment'=>$getTemplateValue,'update_time'=>time()];
                    
                    if($v['demand_type'] == 2 && $disposeStatus2 == 1) $this->model->where('account_id',$v['account_id'])->where('bm',$v['bm'])->update(['new_status'=>2]);
                }                  

                if(!empty($bmData))$servicesBasics->dbBatchUpdate('ba_bm',$bmData, 'id');
                // $this->model->whereIn('id',array_column($bmList,'id'))->update(['new_status'=>$newStatus,'status'=>$status,'dispose_type'=>$disposeStatus,'comment'=>$comment,'update_time'=>time()]);                
                // if(!empty($accountTypeIds)) DB::table('ba_account')->whereIn('account_id',$accountTypeIds)->update(['dispose_status'=>$disposeStatus2]);

                $result = true;
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
    }

    public function progress(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $comment = $this->request->param('comment',null,null)??'';

                if(empty($comment)) throw new \Exception("评论必填！");

                $ids = $this->model->whereIn('id',$ids)->select()->toArray();

                $progressData = [];
                $commentValue = '';
                foreach($ids as $v){
                    if($status == 1)$commentValue = '提交跟进中:'.$comment;
                    else if($status == 2) $commentValue = '处理跟进中:'.$comment;
                    $progressData[] = [
                        'bm_id'=>$v['id'],
                        'comment'=>$commentValue,
                        'create_time'=>time()
                    ];
                }
                DB::table('ba_bm_progress')->insertAll($progressData);
                $this->model->whereIn('id',array_column($ids,'id'))->update(['comment'=>$comment,'operate_admin_id'=>$this->auth->id,'update_time'=>time()]);

                $result = true;
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
    }

    public function progressList()
    {
        $id = $this->request->get('id');
        try {
            $result = DB::table('ba_bm_progress')->where('bm_id',$id)->order('id','desc')->select()->toArray();
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }
        return $this->success('',['list'=>$result]);
    }

    

    public function getBmAnnouncement(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
            $this->success('', [
                'list'   => [],
                'total'  => 0,
            ]);
        }

        if($this->auth->getGroups())

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['bm.demand_type','IN',[1,2]]);
        array_push($where,['bm.status','IN',[0,1]]);
        array_push($where,['bm.dispose_type','IN',[0]]);
        // $whereOr = [];
        //array_push($whereOr,['bm.update_time','<',(time() - 3600)]);
        //array_push($whereOr,['bm.update_time', 'null', null]);
        
        $bmShieldList = DB::table('ba_bm_shield')->column('bm');

        if(!empty($bmShieldList)) array_push($where,['accountrequest_proposal.bm','not in',$bmShieldList]);

        $res = $this->model
            ->field('bm.id,accountrequest_proposal.serial_name account_name')
            ->alias('bm')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal', 'accountrequest_proposal.account_id = bm.account_id')
            ->where($where)
            ->where(function ($query){
                $query->where('bm.update_time', 'null', null)
                ->whereOr('bm.update_time', '<', (time() - 3600));
            })
            ->paginate();

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function batchAdd2()
    {
        $this->error('该功能暂未开放');
        $result = false;
        try {
            $data = $this->request->post();
            $accountIds = $data['account_ids']??[];
            $bmList = $data['bm_list']??[];
            $demandType = $data['demand_type']??1;

            $accountListResult = DB::table('ba_account')->whereIn('account_id',$accountIds)->select()->toArray();


            //是否已经绑定过且没有解绑 （状态等于1 AND demand_type = 1 AND new_status = 1）
            //是有已经提交了需求，在处理中(status = 0 OR dispose_type = 0)
            
            
            if(empty($accountListResult) || empty($bmList)) throw new \Exception("未找到账户ID");
            

            $dataList = array_merge(...array_map(function($v1) use ($bmList,$demandType){
                return array_map(function($v2) use ($v1,$demandType){
                    if($demandType == 1)
                    {  
                        $adminId = $this->auth->id;
                        $result = DB::table('ba_bm')
                        ->where([
                            ['status','=',1],
                            ['demand_type','=',1],
                            ['new_status','=',1],
                            ['account_id','=',$v1['account_id']],
                            ['bm','=',$v2],
                            ['admin_id','=',$adminId],
                        ])->find();


                        $result1 = DB::table('ba_bm')
                        ->where([
                            ['account_id','=',$v1['account_id']],
                            ['bm','=',$v2],
                            ['admin_id','=',$adminId],
                        ])->where(function($query){
                            $query->whereOr([
                                ['status','=',0],
                                ['dispose_type','=',0]
                            ]);
                        })
                        ->find();
                        if(!empty($result) || !empty($result1)) throw new \Exception("该账户已经提交过需求，不可重复提交！({$v1['account_id']}-{$v2})");   
                    }
                    
                    $bmType = 1;
                    if(filter_var($v2, FILTER_VALIDATE_EMAIL) !== false) $bmType = 2;

                    $data = [
                        'demand_type'=>$demandType,
                        'account_id'=>$v1['account_id'],
                        'bm'=>$v2,
                        'bm_type'=>$bmType,
                        'account_name'=>$v1['name'],
                        'admin_id'=>$this->auth->id,
                        'create_time'=>time()
                    ];
                    return $data;
                },$bmList);
            },$accountListResult));
            
            // dd($dataList); 

            $result = $this->model->insertAll($dataList);
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            $this->error($th->getMessage());
        }
        if ($result !== false) {
            $this->success(__('Added successfully'));
        } else {
            $this->error(__('No rows were added'));
        }
    }

    public function batchAdd(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));

            $accountList = $data['account_list'];
            $bmList = $data['bm_list'];

            $bmList = array_unique($bmList);

            if(empty($accountList) || empty($bmList)) $this->error('参数错误');
            if(count($accountList) > 100) $this->error('批量添加不能超过100条');

            try {
                $errorList = [];
                $dataList = [];

                $adminId = $this->auth->id;
                $accountListC = DB::table('ba_account')->alias('account')
                ->field('accountrequest_proposal.account_id,accountrequest_proposal.status')
                ->whereIn('account.account_id',$accountList)
                ->where('account.admin_id',$adminId)
                ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
                ->select()
                ->toArray();

                $nOTConsumptionStatus = config('basics.NOT_consumption_status');

                foreach($accountListC as $k => $v){
                    if(in_array($v['status'],$nOTConsumptionStatus))
                    {
                        $errorList[] = ['bm'=>'(账户ID)'.$v['account_id'],'msg'=>'该账户已经终止使用，不可操作，请联系管理员！'];
                        unset($accountListC[$k]);
                    }
                }

                $accountListC = array_column($accountListC,'account_id');

                if(count($accountList) != count($accountListC)) $errorList[] = ['bm'=>'','msg'=>'你填写的账户ID我们只找到部分，未找到的已经跳过!'];

                $bmListC = [];
                foreach($bmList as $v){
                    if(filter_var($v, FILTER_VALIDATE_EMAIL)){
                        $bmListC[] = [
                            'bm'=>$v,
                            'bm_type'=>2
                        ];
                    }else if (preg_match('/^\d+$/', $v)) {
                        $bmListC[] = [
                            'bm'=>$v,
                            'bm_type'=>1
                        ];
                    }else{
                        $errorList[] = ['bm'=>$v,'msg'=>'BM格式错误,请填写正确的BM或邮箱!'];
                    }
                }
                
                foreach($accountListC as $v)
                {
                    foreach($bmListC as $v2)
                    {
                        $bm = Db::table('ba_bm')
                            ->where('account_id', $v)
                            ->where('bm', $v2['bm'])
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

                        // $bm = Db::table('ba_bm')
                        //     ->where('account_id', $v)
                        //     ->where('bm', $v2['bm'])
                        //     ->where('demand_type', 1)
                        //     ->where('new_status', 1)                            
                        //     ->where('dispose_type', 1)
                        // ->value('bm');

                        if(!empty($bm)) {
                            $errorList[] = ['bm'=>'(账户)'.$v.' - (BM)'.$v2['bm'],'msg'=>'该BM已经提交过需求，不需要重复提交!'];
                            continue;
                        }

                        $dataList[] = [
                            'demand_type'=>1,
                            'account_id'=>$v,
                            'bm'=>$v2['bm'],
                            'bm_type'=>$v2['bm_type'],
                            'account_name'=>'',
                            'admin_id'=>$adminId,
                            'create_time'=>time()
                        ];
                    }      
                }

                DB::table('ba_bm')->insertAll($dataList);
            } catch (\Exception $th) {
                $this->error('参数错误!');
            }
            $this->success(__('Added successfully'),['error_list'=>$errorList]);
        }

        $this->error(__('Parameter error'));
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */

    //bm状态修改和发送消息
    public function bmOperation($v=[])
    {
        $noticeGroup = Db::table('ba_admin')->where('id',$v['admin_id'])->value('notice_group');
        $value =  DB::table('ba_account')->where('account_id',$v['account_id'])->update(['status'=>4]);//开户绑定类型处理完成
        if(!empty($noticeGroup)){
            $v['notice_group'] =  $noticeGroup;
            if($value) (new QYWXService())->send_notification($v);
        }
    }

}