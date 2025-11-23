<?php

namespace app\admin\model;

use Throwable;
use think\Model;
use think\facade\Cache;

/**
 * 审计异常列表
 */
class AuditException extends Model
{
    protected $name = 'audit_exception';
    protected $autoWriteTimestamp = true;
}