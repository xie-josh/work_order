<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\admin\model\fb\ConsumptionModel;
use app\common\controller\Backend;

class Consumption extends Backend
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

        if($this->auth->type == 4) $this->success('', [
            'list'   => [],
            'total'  => 0,
            'remark' => '',
        ]);

        // sart_time
        // end_time
        $startTime = $this->request->get('start_time');
        $endTime = $this->request->get('end_time');

        if(!empty($startTime) || !empty($endTime))
        {
            // $sartTime = date('Y-m-d',strtotime('-7 days'));
            // $endTime = date('Y-m-d',time());

        }else{
            $startTime = date('Y-m-d',strtotime('-7 days'));
            $endTime = date('Y-m-d',time());
        }
        

        array_push($where,['date_start','>=',$startTime]);
        array_push($where,['date_start','<=',$endTime]);

        $res = $this->model
        ->field('ROUND(sum(dollar),2) dollar,date_start')
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->group('date_start')
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);

    }
    
}