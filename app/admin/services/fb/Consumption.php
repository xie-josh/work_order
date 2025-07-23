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

    public function getTotalDollar($adminId)
    {
        $model = $this->model;
        $result = $model->field('sum(dollar) totalDollar')->where('admin_id', $adminId)->group('admin_id')->find();  
        return isset($result['totalDollar']) ? $result['totalDollar'] : 0;
    }

}