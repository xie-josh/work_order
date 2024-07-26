<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户请求
 */
class Accountrequest extends Backend
{
    /**
     * Accountrequest模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\Accountrequest
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'status', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\Accountrequest();
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
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function audit(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = explode(',',$data['ids']);
                $id = $data['id'];
                $row = $this->model->where('id',$id)->find();

                if(empty($row)) throw new \Exception("Error Processing Request");
                
                $this->model->where('id',$id)->update(['status'=>1]);

                $dataList  = [];
                foreach($ids as $v){
                    $dataList[] = [
                        'accountrequest_id'=>$id,
                        'bm'=>$row['bm'],
                        'admin_id'=>$row['admin_id'],
                        'status'=>0,
                        'account_id'=>$v,
                        'create_time'=>time()
                    ];
                }

                Db::table('ba_accountrequest_proposal')->insertAll($dataList);

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