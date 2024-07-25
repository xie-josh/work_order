<?php

namespace app\admin\model\addaccountrequest;

use think\Model;

/**
 * Accountrequest
 */
class Accountrequest extends Model
{
    // 表名
    protected $name = 'accountrequest';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
}