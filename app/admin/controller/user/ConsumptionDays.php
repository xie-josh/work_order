<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\admin\model\fb\ConsumptionModel;
use app\common\controller\Backend;

class ConsumptionDays extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];

    protected string|array $quickSearchField = 'name';

    protected array $noNeedPermission = ['index'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConsumptionModel();
    }

    public function index(): void
    {
       if ($this->request->param('select')) {
            $this->select();
        }
       
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($where,['company_id','=',$this->auth->company_id]);

        $res = $this->model
        ->field('ROUND(sum(consumption.dollar), 2) dollar,consumption.date_start,accountrequest_proposal.serial_name,consumption.account_id')
        ->alias('consumption')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=consumption.account_id')
        ->where($where)
        ->group('consumption.date_start')
        ->order('date_start','desc')
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);

    }
    
}