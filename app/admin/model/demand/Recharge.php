<?php

namespace app\admin\model\demand;

use think\Model;

/**
 * Recharge
 */
class Recharge extends Model
{
    // 表名
    protected $name = 'recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getNumberAttr($value): float
    {
        return (float)$value;
    }
}