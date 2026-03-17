<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\auth\JiabaiRegionModel;
use app\common\controller\Backend;

class JiabaiRegion extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new JiabaiRegionModel();
    }
    
}