<?php

namespace app\admin\controller;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户管理
 */
class Account extends Backend
{
    /**
     * Account模型对象
     * @var object
     * @phpstan-var \app\admin\model\Account
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_id', 'admin_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];
    protected array $noNeedPermission = ['accountCountMoney','editIs_'];
    protected string|array $quickSearchField = ['id'];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Account();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {

        $this->quickSearchField = 'account_id';
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $status = $this->request->get('status');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        if($status == 1){
            array_push($where,['account.status','=',1]);
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){
            
            $bmList = [];
            if($status == 1){
                $accountIds = array_column($dataList,'account_id');
                $resultBm = DB::table('ba_bm')->where('status',1)->whereIn('account_id',$accountIds)->select()->toArray();
                foreach($resultBm as $v){
                    $bmList[$v['account_id']][] = $v['bm'];
                }
            }
            
            foreach($dataList as &$v){
                $v['bm_list'] = $bmList[$v['account_id']]??[];
            }
        }
        $res->visible(['admin' => ['username']]);

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
                $data['admin_id'] = $this->auth->id;
                // $data['account_id'] = $this->generateUniqueNumber();
                $result = $this->model->save($data);
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
            $this->error('已经审核不可在修改');
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



    function generateUniqueNumber() {
        // 获取当前的微秒时间戳
        $microtime = microtime(true);
        
        // 提取整数部分（秒）和小数部分（微秒）
        $seconds = floor($microtime);
        $milliseconds = ($microtime - $seconds) * 1000;
        
        // 将秒和毫秒组合成一个整数
        $timestamp = $seconds . sprintf('%03d', $milliseconds);
        
        // 生成一个随机数来填充剩余的位数
        $randomNumber = mt_rand(1000, 9999); // 生成一个4位的随机数
        
        // 合并时间戳和随机数
        $uniqueNumber = $timestamp . $randomNumber;
        
        // 截取前16位
        return substr($uniqueNumber, 0, 16);
    }


    public function audit(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $adminId = $data['admin_id']??0;
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();

                if($status == 1){
                    foreach($ids as $v){
                        $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('admin_id',$adminId)->where('status',0)->find();
                        if(empty($accountrequestProposal))  continue;//throw new \Exception("该渠道暂时没有账号可以分配");
                        $accountId = $accountrequestProposal['account_id'];
                        
                        $result = $this->model->where('id',$v['id'])->update(['account_admin_id'=>$adminId,'status'=>$status,'account_id'=>$accountId]);
    
                        DB::table('ba_accountrequest_proposal')->where('id',$accountrequestProposal['id'])->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);

                        if(!empty($v['money'])) DB::table('ba_recharge')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'type'=>1,'number'=>$v['money'],'status'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                        if(!empty($v['bm'])) DB::table('ba_bm')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$v['bm'],'demand_type'=>1,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                    }
                }else{
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status]);
                }

                $this->model->whereIn('id',array_column($ids,'id'))->update(['money'=>0,'is_'=>1]);
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

    public function disposeStatus(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->column('id'); 

                $result = $this->model->whereIn('id',$ids)->update(['dispose_status'=>$status]);

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

    public function editIs_(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->column('id'); 

                $result = $this->model->whereIn('id',$ids)->update(['is_'=>$status]);

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


    function accountCountMoney()
    {
        if($this->auth->isSuperAdmin()){
            $money = $this->model->where('is_',1)->sum('money');
        }else{
            $money = $this->model->where('is_',1)->where('admin_id',$this->auth->id)->sum('money');
        }
        $this->success('',['money'=>$money]);
    }



    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}