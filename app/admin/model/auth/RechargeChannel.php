<?php

namespace app\admin\model\auth;

use think\Model;

/**
 * RechargeChannel
 */
class RechargeChannel extends Model
{
    // 表名
    protected $name = 'recharge_channel';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

}