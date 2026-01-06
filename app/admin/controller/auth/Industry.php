<?php

namespace app\admin\controller\auth;

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
    
    protected array $noNeedPermission = [];

    protected string|array $quickSearchField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new IndustryModel();
    }
    
}