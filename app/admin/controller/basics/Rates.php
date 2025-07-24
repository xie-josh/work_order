<?php

namespace app\admin\controller\basics;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use app\admin\model\User as UserModel;

class Rates extends Backend
{
    /**
     * @var object
     * @phpstan-var Rates
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\basics\RatesModel();
    }

    public function add()   :void{$this->error('功能暂未开放！');}
    public function edit()   :void{$this->error('功能暂未开放！');}
    public function del(array $ids = []): void{$this->error('功能暂未开放！');}

}