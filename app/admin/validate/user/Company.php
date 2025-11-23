<?php

namespace app\admin\validate\user;

use think\Validate;

class Company extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'company_name'  => 'require|unique:company',
        'email'         => 'require',
        'mobile'        => 'require|unique:company',
    ];

    /**
     * 提示消息
     */
    protected $message = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => ['company_name', 'email', 'mobile'],
    ];

}
