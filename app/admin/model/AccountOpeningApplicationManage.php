<?php

namespace app\admin\model;

use think\Model;

/**
 * AccountTk
 */
class AccountOpeningApplicationManage extends Model
{
    // 表名
    protected $name = 'account_opening_application_manage';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function getIndustryIdsAttr($value)
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return array_values(array_unique(array_filter(array_map('intval', explode(',', $value)), fn($v) => $v !== 0)));
        }
        return $value;
    }

    public function setIndustryIdsAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public function getCurrencyListAttr($value)
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return explode(',', $value);
        }
        return $value;
    }

    public function setCurrencyListAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

}