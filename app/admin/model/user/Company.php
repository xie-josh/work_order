<?php

namespace app\admin\model\user;

use think\Model;

/**
 * CompanyModel
 */
class Company extends Model
{
        /**
     * @var string 自动写入时间戳
     */
    protected $autoWriteTimestamp = true;
    // 表名
    protected $name = 'company';


    public function getDeleteTimeAttr($value)
    {
        $h = floor($value / 3600);
        return $h;
    }

    public function setDeleteTimeAttr($value)
    {
        $h = floor($value * 3600);
        return $h;
    }

}