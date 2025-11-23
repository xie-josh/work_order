<?php

namespace app\admin\validate\user;

use think\Validate;

class Team extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'team_name'    => 'require',
        'team_money'   => 'require|number',
        'company_id'   => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  =>  ['team_name','company_id'],
        'edit' =>  ['team_name','company_id']
    ];

}
