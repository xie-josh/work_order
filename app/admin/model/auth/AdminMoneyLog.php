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

    public function getImagesAttr($value): array
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return explode(',', $value);
        }
        return $value;
    }

    public function setImagesAttr($value): string
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
    
    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'company_id', 'id');
    }

    public function company(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\user\Company::class, 'company_id', 'id');
    }
}