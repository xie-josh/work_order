<?php

namespace app\admin\validate\user;

use think\Validate;

class Admin extends Validate
{
    protected $failException = true;

    protected $rule = [
        'username'  =>   'require|unique:admin',
        'password'  =>   'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$',
        // 'nickname'  =>   'require',
        'email'     =>   'require|unique:admin',
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
        'add' => ['username', 'nickname', 'password','email'],
        'edit' => ['username', 'nickname','email'],
        'register' => ['password','email']
    ];

}
