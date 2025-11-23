<?php

namespace app\admin\model\user;

use think\Model;

/**
 * CompanyModel
 */
class Rate extends Model
{
        /**
     * @var string 自动写入时间戳
     */
    protected $autoWriteTimestamp = true;
    // 表名
    protected $name = 'rate';

}