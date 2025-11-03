<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Db;

class AccountRecyclePending extends Backend
{
    /**
     * @var object
     * @phpstan-var AccountChannel
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['id'];
    protected array $noNeedPermission = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountRecyclePending();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
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

}