<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\auth\IndustryModel;
use app\common\controller\Backend;

class Industry extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new IndustryModel();
    }

       /**
     * 查看
     * @throws Throwable
     */
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
            ->order('update_time','ase')
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    
}