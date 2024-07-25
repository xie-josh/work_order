<?php

namespace app\admin\validate\accountrequest;

use think\Validate;

class Proposal extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
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
        'add'  => [],
        'edit' => [],
    ];

}
