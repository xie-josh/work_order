<?php

namespace app\admin\model;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class AccountType extends Model
{
    protected $name = 'account_type';
    protected $autoWriteTimestamp = true;
}