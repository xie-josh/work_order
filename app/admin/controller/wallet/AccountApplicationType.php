<?php

namespace app\admin\controller\wallet;

use Throwable;
use app\common\controller\Backend;

/**
 * 入账申请
 */
class AccountApplicationType extends Backend
{
    /**
     * AccountApplication模型对象
     * @var object
     * @phpstan-var \app\admin\model\wallet\AccountApplicationType
     */
    protected object $model;

    protected array|string $preExcludeFields = [];

    protected array $withJoinTable = [];
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = ['id'];

    protected bool|string|int $dataLimit = '';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\wallet\AccountApplicationType();
    }



    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}