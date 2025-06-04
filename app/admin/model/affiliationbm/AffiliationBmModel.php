<?php

namespace app\admin\model\affiliationbm;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class AffiliationBmModel extends Model
{
    protected $name = 'affiliation_bm';

       // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function getPutLableAttr($value, $data): array
    {
        if(empty($value)) return [];
        return array_map('intval', explode(',', $value));
    }

    public function setPutLableAttr($value): string
    {
        return implode(',', $value);
    }
}