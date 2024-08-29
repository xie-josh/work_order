<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use think\facade\Db;
use Throwable;

/**
 * 充值需求
 */
class Recharge extends Backend
{
    /**
     * Recharge模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\Recharge
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_name', 'status', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];
    protected array $withJoinTable = ['accountrequestProposal'];

    protected bool|string|int $dataLimit = 'parent';

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

        foreach($where as $k => &$v){
            if($v[0] == 'recharge.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
            }
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
                $account = Db::table('ba_account')->where('account_id',$data['account_id'])->where('admin_id',$this->auth->id)->where('status',4)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID或账户不可用");
                
                
                if($data['type'] == 1){

                    if($data['number'] <= 0) throw new \Exception("充值金额不能小于零");

                    $admin = Db::table('ba_admin')->where('id',$account['admin_id'])->find();
                    $usableMoney = ($admin['money'] - $admin['used_money']);
                    if($usableMoney <= 0 || $usableMoney < $data['number']) throw new \Exception("余额不足,请联系管理员！");

                    //DB::table('ba_account')->where('id',$account['id'])->inc('money',$data['number'])->update(['update_time'=>time()]);
                    DB::table('ba_admin')->where('id',$account['admin_id'])->inc('used_money',$data['number'])->update();
                }elseif(in_array($data['type'],[3,4])){
                    $recharge = $this->model->where('account_id',$data['account_id'])->order('id','desc')->find();
                    if(!empty($recharge) && in_array($recharge['type'],[3,4])) throw new \Exception("待清零中，不需要重复提交");
                }
                
                $data['account_name'] = $account['name'];
                $data['admin_id'] = $this->auth->id;

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
                $money = $data['money']??0;
                $type = $data['type']??0;

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();

                $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time()]);

                if($status == 1){
                    foreach($ids as $v){
                        if($v['type'] == 1){
                            // $admin = Db::table('ba_admin')->where('id',$v['admin_id'])->find();
                            // $usableMoney = ($admin['money'] - $admin['used_money']);
                            // if($usableMoney <= 0 || $usableMoney < $v['number']) throw new \Exception("余额不足,请联系管理员！");

                            DB::table('ba_account')->where('account_id',$v['account_id'])->inc('money',$v['number'])->update(['update_time'=>time()]);
                            // DB::table('ba_admin')->where('id',$v['admin_id'])->inc('used_money',$v['number'])->update();
                        }elseif($v['type'] == 2){
                            DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();
                        }elseif($v['type'] == 3 || $v['type'] == 4){
                            // $money = DB::table('ba_account')->where('account_id',$v['account_id'])->where('status',1)->value('money');
                            $data = [
                                'number'=>$money,
                                'type'=>$type
                            ];
                            $this->model->where('id',$v['id'])->update($data);
                            DB::table('ba_account')->where('account_id',$v['account_id'])->update(['money'=>0,'update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$money)->update();
                        }
                    }
                }else{
                    foreach($ids as $v){
                        if($v['type'] == 1){
                            //DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();
                        }
                    }
                }
                
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