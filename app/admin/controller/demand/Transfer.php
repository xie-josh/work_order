<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;

/**
 * 转移需求
 */
class Transfer extends Backend
{
    /**
     * Transfer模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\Transfer
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'start_account_name', 'end_account_name', 'admin_id', 'audit', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Transfer();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}