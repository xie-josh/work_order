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
    protected $autoWriteTimestamp = true;


    public function getMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getCommentAttr($value): string
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
}