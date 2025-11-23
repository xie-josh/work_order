<?php
namespace app\admin\services\fb;

use app\services\FacebookService;

class Consumption
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\fb\ConsumptionModel();
        $this->auth = $auth??new \stdClass;
    }

    public function getTotalDollar($companyId)
    {
        $model = $this->model;
        $result = $model->field('sum(dollar) totalDollar')->where('company_id', $companyId)->group('company_id')->find();
        return isset($result['totalDollar']) ? $result['totalDollar'] : 0;
    }

    public function thePreviousDay($companyId)
    {
        $day = date('Y-m-d', strtotime('-1 day'));
        $where['company_id'] = $companyId;
        $where['date_start'] = $day;
        $result = $this->model->field('sum(dollar) totalDollar')->where($where)->find();
        return isset($result['totalDollar']) ? $result['totalDollar'] : 0;
    }

}