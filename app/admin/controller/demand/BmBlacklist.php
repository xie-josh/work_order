<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;

class BmBlacklist extends Backend
{
    /**
     * @var object
     * @phpstan-var BmBlacklist
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];
    protected array $noNeedPermission = ['index'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\BmBlackListModel();
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
            ->paginate(10);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}