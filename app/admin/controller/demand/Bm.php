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

    protected array $noNeedPermission = ['disposeStatus','index','getBmList','getBmAnnouncement','progressList'];

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
        //dd($dataList);
        if($dataList){
            
            $adminIds = [];
            foreach($dataList as $v){
                if(!empty($v['accountrequestProposal']['admin_id'])) $adminIds[] = $v['accountrequestProposal']['admin_id'];
            }        
            $admin = DB::table('ba_admin')->whereIn('id',$adminIds)->select()->toArray();
            
            $adminList = [];
            foreach($admin as $v){
                $adminList[$v['id']] = $v['nickname'];
            }
           
            
            foreach($dataList as &$v){
                $v['account_requestProposal_admin'] = $adminList[$v['accountrequestProposal']['admin_id']??0]??'';
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

                $account = Db::table('ba_account')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID");

                $accountrequestProposal = Db::table('ba_accountrequest_proposal')->where('account_id',$accountId)->value('status');
                if(empty($accountrequestProposal) || $accountrequestProposal == 99) throw new \Exception("未找到账户或该账户已经终止使用，不可操作，请联系管理员！");

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

                        if (filter_var($v, FILTER_VALIDATE_EMAIL) && $bmType != 2) throw new \Exception("BM与选择的类型不匹配,请重新选择！");

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

                $progressData = [];
                $commentValue = '';
                foreach($ids as $v){
                    switch ($status) {
                        case '1':
                            $disposeStatus  = 2;
                            $commentValue = '已提交:'.$comment;
                            break;
                        case '2':
                            $disposeStatus = 3;
                            $commentValue = '提交异常:'.$comment;
                            break;
                        case '3':
                            $disposeStatus = 1;
                            $commentValue = '处理完成:'.$comment;
                            break;
                        default:
                            break;
                    }
                    DB::table('ba_account')->where('account_id',$v['account_id'])->update(['dispose_status'=>$disposeStatus]);

                    $progressData[] = [
                        'bm_id'=>$v['id'],
                        'comment'=>$commentValue,
                        'create_time'=>time()
                    ];
                }

                $bmData = [];
                if($status == 3){
                    $bmData = ['status'=>1,'dispose_type'=>1,'comment'=>$comment,'update_time'=>time()];
                }else{
                    $bmData = ['status'=>$status,'comment'=>$comment,'update_time'=>time()];
                }
                $result = $this->model->whereIn('id',array_column($ids,'id'))->update($bmData);
                
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
                $comment = $data['comment']??'';

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray(); 

                $accountIds = [];
                $progressData = [];
                $commentValue = '';
                foreach($ids as $v){
                    $accountIds[] = $v['account_id'];
                    if($v['demand_type'] == 2 && $status == 1){
                        $this->model->where('account_id',$v['account_id'])->where('bm',$v['bm'])->update(['new_status'=>2]);
                    }
                    
                    if($status == 1) $commentValue = '处理完成:'.$comment;
                    else if($status == 2) $commentValue = '处理异常:'.$comment;

                    $progressData[] = [
                        'bm_id'=>$v['id'],
                        'comment'=>$commentValue,
                        'create_time'=>time()
                    ];
                }
                
                $this->model->whereIn('id',array_column($ids,'id'))->update(['dispose_type'=>$status,'comment'=>$comment,'update_time'=>time()]);

                if($status == 1)$disposeStatus  = 1;
                else $disposeStatus = 4;
                DB::table('ba_account')->whereIn('account_id',$accountIds)->where('dispose_status',2)->update(['dispose_status'=>$disposeStatus]);

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

    public function progress(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $comment = $data['comment']??'';

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
                $this->model->whereIn('id',array_column($ids,'id'))->update(['comment'=>$comment,'update_time'=>time()]);

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

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where(function ($query){
                $query->where('bm.update_time', 'null', null)
                ->whereOr('bm.update_time', '<', (time() - 3600));
            })
            ->order($order)
            ->paginate(1);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}