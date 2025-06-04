<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\User as UserModel;

class AffiliationBm extends Backend
{
    /**
     * @var object
     * @phpstan-var AffiliationBm
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['id'];
    protected array $noNeedPermission = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\affiliationbm\AffiliationBmModel();
    }


}