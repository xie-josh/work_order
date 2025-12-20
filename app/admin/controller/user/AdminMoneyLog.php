<?php

namespace app\admin\controller\user;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账号充值记录
 */
class AdminMoneyLog extends Backend
{
    /**
     * AdminMoneyLog模型对象
     * @var object
     * @phpstan-var \app\admin\model\user\AdminMoneyLog
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $noNeedPermission = ['index'];

    protected array $withJoinTable = ['company'];

    protected string|array $quickSearchField = ['id'];

    protected bool|string|int $dataLimit = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\auth\AdminMoneyLog();
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
        array_push($where,['company_id','=',$this->auth->company_id]);
        array_push($where,['status','=',1]);
        array_push($where,['type','in',[1,4]]);
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order('create_time','desc')
            ->paginate($limit);
        $res->visible(['admin' => ['username','nickname']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}