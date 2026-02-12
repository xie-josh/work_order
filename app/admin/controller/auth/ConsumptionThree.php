<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\fb\ConsumptionTrusteeshipModel;
use think\facade\Cache;


class ConsumptionThree extends Backend
{
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConsumptionTrusteeshipModel();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res   = Db::table('ba_account_consumption_trusteeship')
        ->field("trusteeship,sum(dollar) dollar")
        ->where($where)
        ->group('trusteeship')
        ->order('trusteeship desc')
        ->paginate(15);//->select()->toArray();

        $dataList = [];
        if($res) {
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $dataList[] = [
                    'trusteeship' => $v['trusteeship'],
                    'dollar' => number_format($v['dollar'], 2)
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