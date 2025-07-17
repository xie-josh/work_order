<?php

namespace app\admin\model\demand;

use think\Model;
use think\facade\Db;

/**
 * BmShieldModel
 */
class BmShieldModel extends Model
{
    // 表名
    protected $name = 'bm_shield';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

}