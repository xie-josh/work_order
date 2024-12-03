<?php

namespace app\admin\controller\demand;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;


class RechargeTk extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\RechargeTkModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = [];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = [];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\RechargeTkModel();
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

                $accountId = $data['account_id']??'';
                $account = Db::table('ba_account_tk')->where('account_id',$accountId)->where('admin_id',$this->auth->id)->where('status',4)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID");

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

    public function audit()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                if(empty($ids) || empty($status)) throw new \Exception("Params required !");
                
                $rechargeServices = new \app\admin\services\demand\RechargeTk($this->auth);
                $rechargeServices->audit($data);
                
                $result = true;
                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
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