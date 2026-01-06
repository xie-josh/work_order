<?php

namespace app\admin\model\auth;

use think\Model;

/**
 * PlatformRate
 */
class IndustryModel extends Model
{
    // 表名
    protected $name = 'industry';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    
    public function getTimeZoneListAttr($value)
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return explode(',', $value);
        }
        return $value;
    }

    public function setTimeZoneListAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

}