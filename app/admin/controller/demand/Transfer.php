<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use think\facade\Db;
use Throwable;

/**
 * 转移需求
 */
class Transfer extends Backend
{
    /**
     * Transfer模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\Transfer
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'start_account_name', 'end_account_name', 'admin_id', 'audit', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Transfer();
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
                $startAccountId = $data['start_account_id'];
                $endAccountId = $data['end_account_id'];

                $accountIds = [$endAccountId,$startAccountId];
                $account = Db::table('ba_account')->whereIn('account_id',$accountIds)->select()->toArray();
                if(count($account) != 2) throw new \Exception("未找到该账户ID");

                $accountList = [];
                foreach($account as $v){
                    $accountList[$v['account_id']] = $v;
                }
                $data['start_account_name'] = $accountList[$startAccountId]['name'];
                $data['end_account_name'] = $accountList[$endAccountId]['name'];
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



    public function audit(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('audit',0)->select()->toArray();

                $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['audit'=>$status,'update_time'=>time()]);

                if($status == 1){
                    foreach($ids as $v){
                        DB::table('ba_account')->where('account_id',$v['end_account_id'])->inc('money',$v['money'])->update(['update_time'=>time()]);
                        DB::table('ba_account')->where('account_id',$v['start_account_id'])->dec('money',$v['money'])->update(['update_time'=>time()]);
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