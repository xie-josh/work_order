<?php

namespace app\admin\model\addaccountrequest;

use think\Model;

/**
 * Accountrequest
 */
class AccountChannel extends Model
{
    // 表名
    protected $name = 'account_channel';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function setIsNameKeyAttr($value)
    {
        return json_encode($value);
    }

    public function getIsNameKeyAttr($value)
    {
        return json_decode($value,true);
    }

}