<?php

namespace app\admin\model;

use think\Model;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $name = 'account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

}