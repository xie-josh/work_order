<?php

namespace app\admin\controller;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Db;

class AccountType extends Backend
{
    /**
     * @var object
     * @phpstan-var AccountType
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];
    protected array $noNeedPermission = ['index'];
    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\AccountType();
    }

    public function getAssociationAccountType()
    {
        $admin = $this->request->get('admin_id');
        if(empty($admin)) $this->error('admin id request !');
        $result = DB::table('ba_association_account_type')->where('admin_id',$admin)->column('account_type_id');
        $this->success('',['row'=>$result]);
    }



    public function associationAccountType()
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
                $adminId = $data['admin_id'];
                $accountTypeList = $data['account_type_list'];

                $dataList = [];
                foreach($accountTypeList as $v){
                    $dataList[] = [
                        'admin_id'=>$adminId,
                        'account_type_id'=>$v
                    ];
                }

                DB::table('ba_association_account_type')->where('admin_id',$adminId)->delete();
                $result = DB::table('ba_association_account_type')->insertAll($dataList);

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
    
}