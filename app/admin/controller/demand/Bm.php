<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use think\facade\Db;
use Throwable;

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

    protected array|string $preExcludeFields = ['id', 'account_name', 'status', 'dispose_type','getBmList', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = ['disposeStatus','index'];

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
                $checkList = $data['checkList']??[];

                if($bm) array_push($checkList,$bm);

                $account = Db::table('ba_account')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID");


                //dd($checkList);
                $dataList = [];
                foreach($checkList as $v){
                    $dataList[] = [
                        'demand_type'=>$demandType,
                        'account_id'=>$accountId,
                        'bm'=>$v,
                        'account_name'=>$account['name'],
                        'admin_id'=>$this->auth->id,
                        'create_time'=>time()
                    ];
                }
                //$data['account_name'] = $account['name'];
                //$data['admin_id'] = $this->auth->id;

                $result = $this->model->insertAll($dataList);

                //$result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
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

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->column('id');

                $result = $this->model->whereIn('id',$ids)->update(['status'=>$status,'update_time'=>time()]);
                
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

            //$bmList =  DB::table('ba_bm')->where('account_id',$accountId)->order('demand_type')->group('account_id,bm')->select()->toArray();

            $result = Db::table('ba_bm')
            ->alias('t1')
            ->where('t1.account_id',$accountId)
            ->join('(SELECT account_id, bm, MAX(id) AS max_id FROM ba_bm GROUP BY account_id, bm) t2', 't1.id = t2.max_id')
            ->order('t1.id', 'desc')
            ->having('demand_type = 1')
            ->select()->toArray();

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

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray(); 

                foreach($ids as $v){
                    DB::table('ba_account')->where('account_id',$v['account_id'])->update(['dispose_status'=>1]);
                }

                $this->model->whereIn('id',array_column($ids,'id'))->update(['dispose_type'=>$status,'update_time'=>time()]);

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

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}