<?php

namespace app\admin\validate;

use think\Validate;

class AccountChannel extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name'       => 'require|unique:AccountChannel',
        'is_name'    => 'require',
    ];

    /**
     * 验证提示信息
     * @var array
     */
    protected $message = [];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['name', 'is_name'],
    ];

}