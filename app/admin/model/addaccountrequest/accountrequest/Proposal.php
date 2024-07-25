<?php

namespace app\admin\model\addaccountrequest\accountrequest;

use think\Model;

/**
 * Proposal
 */
class Proposal extends Model
{
    // 表名
    protected $name = 'accountrequest_proposal';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
}