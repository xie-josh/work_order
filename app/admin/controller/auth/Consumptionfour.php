<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\fb\ConsumptionTrusteeshipModel;
use think\facade\Cache;


class Consumptionfour extends Backend
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
        // list($where, $alias, $limit, $order) = $this->queryBuilder();

        $name = $this->request->get('trusteeship');
        if(empty($name)) $this->error('请选择托管人');
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res   = Db::table('ba_account_consumption_trusteeship')
        ->field("trusteeship,dollar,date_start,account_id")
        ->where('trusteeship',$name)
        ->where('date_start','>',0)
        ->order('date_start desc')
        ->paginate(15);//->select()->toArray();

        $dataList = [];
        if($res) {
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $dataList[] = [
                    'trusteeship' => $v['trusteeship'],
                    'dollar' => number_format($v['dollar'], 2),
                    'date_start' => $v['date_start'],
                    'account_id' => $v['account_id'],
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