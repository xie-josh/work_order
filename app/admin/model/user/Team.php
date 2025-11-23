<?php

namespace app\admin\model\user;

use think\Model;

/**
 * TeamModel
 */
class Team extends Model
{
    // 表名
    protected $name = 'team';

    /**
    * @var string 自动写入时间戳
    */
    protected $autoWriteTimestamp = true;

}