<?php

namespace app\admin\model\fb;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class FbLogsModel extends Model
{
    protected $name = 'fb_logs';
    protected $autoWriteTimestamp = true;
}