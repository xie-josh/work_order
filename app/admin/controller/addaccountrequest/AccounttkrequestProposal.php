<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户列管理
 */
class AccounttkrequestProposal extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\AccounttkrequestProposal
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = [];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = [];

    //protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccounttkrequestProposal();
    }


    public function getAccountList()
    {
        $row = $this->model->where('status',0)->field('account_id,country,type')->select()->toArray();

        $typeValue = [1=>'BC',2=>'个人'];
        $dataList = [];
        foreach($row as $v){
            $v['account_value'] = $v['account_id'].'('.$v['country'].')('.$typeValue[$v['type']].')';
            $dataList[] = $v;
        }

        $this->success('', [
            'list'   => $dataList,
        ]);
    }

    public function distribution()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {    
                (new \app\admin\services\addaccountrequest\AccounttkrequestProposal($this->auth))->distribution($data);

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

    function inDistribution()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {                                
                (new \app\admin\services\addaccountrequest\AccounttkrequestProposal($this->auth))->inDistribution($data);

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