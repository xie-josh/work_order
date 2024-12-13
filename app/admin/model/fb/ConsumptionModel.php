<?php

namespace app\admin\model\fb;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class ConsumptionModel extends Model
{
    protected $name = 'account_consumption';
    protected $autoWriteTimestamp = true;
}