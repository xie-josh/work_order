<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\Admin as AdminModel;

class ConsumptionStatistics extends Backend
{
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminModel();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['admin_group_access.group_id','in',[3]]);
        array_push($where,['admin.status','=',1]);
        
        $res = DB::table('ba_admin')
            ->alias('admin')
            ->field('admin.id,admin.nickname,admin.money,admin_group_access.group_id')
            ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')
            ->where($where)
            ->order($order)
            ->paginate($limit)->appends([]);
        $dataList = [];
        if($res) {
            $consumptionService = new \app\admin\services\fb\Consumption();
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $totalDollar = $consumptionService->getTotalDollar($v['id']);
                $money = $v['money'] ?? 0;
                $remainingAmount = bcsub((string)$money,(string)$totalDollar,'2');

                $dataList[] = [
                    'id' => $v['id'],
                    'nickname' => $v['nickname'],
                    'money' => $money,
                    'total_dollar' => $totalDollar,
                    'remaining_amount' => $remainingAmount,
                ];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}