<?php

namespace app\admin\model\basics;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class RatesModel extends Model
{
    protected $name = 'exchange_rate';
    protected $autoWriteTimestamp = true;
}