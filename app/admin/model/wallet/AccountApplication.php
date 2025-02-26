<?php

namespace app\admin\model\wallet;

use think\Model;

/**
 * AccountApplication
 */
class AccountApplication extends Model
{
    // 表名
    protected $name = 'wallet_account_application';

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
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function type(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\wallet\AccountApplicationType::class, 'type_id', 'id');
    }
}