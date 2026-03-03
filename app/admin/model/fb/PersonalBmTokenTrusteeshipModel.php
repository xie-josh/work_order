<?php

namespace app\admin\model\fb;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class PersonalBmTokenTrusteeshipModel extends Model
{
    protected $name = 'fb_personalbm_token_trusteeship';
    protected $autoWriteTimestamp = true;
}