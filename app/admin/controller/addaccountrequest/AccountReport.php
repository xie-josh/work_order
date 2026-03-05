<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户请求
 */
class AccountReport extends Backend
{
    /**
     * Accountrequest模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\Accountrequest
     */
    protected object $model;

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountReport();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
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
            
            $result = false;
            $this->model->startTrans();
            try {
                $temp = $data['account_ids'];
                $data['account_ids'] = implode(',',$data['account_ids']??[]);
                // 模型验证
                $result = $this->model->save($data);
                if ($temp) {
                    $detali = [];
                    foreach ($temp as $accountId) {
                        $detali[] = [
                            'report_id'      => $this->model->id,
                            'account_id'     => $accountId,
                            'create_time'    => date('Y-m-d H:i:s', time()),
                        ];
                    }
                    Db::name('account_report_detali')->insertAll($detali);
                }
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