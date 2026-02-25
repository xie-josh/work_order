<?php

namespace app\admin\model\basics;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class RecyclingGracePeriodModel extends Model
{
    protected $name = 'recycling_grace_period';
    protected $autoWriteTimestamp = true;
}