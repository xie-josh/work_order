<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\common\controller\Backend;

class Recycle extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = 'name';

    // protected bool|string|int $dataLimit = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Account();
    }

    /**
     * 回收列表
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        if($this->auth->type == 4) $this->success('', [
            'list'   => [],
            'total'  => 0,
            'remark' => get_route_remark(),
        ]);
        
        array_push($where,['company_id','=',$this->auth->company_id]);
        $res = DB::table('ba_account_recycle')
            ->field('name,account_id,currency,total_consumption,account_recycle_time,total_up,total_delete,total_deductions')
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