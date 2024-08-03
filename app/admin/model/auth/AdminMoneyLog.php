<?php

namespace app\admin\model\auth;

use think\Model;

/**
 * AdminMoneyLog
 */
class AdminMoneyLog extends Model
{
    // 表名
    protected $name = 'admin_money_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
}