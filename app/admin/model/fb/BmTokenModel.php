<?php

namespace app\admin\model\fb;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class BmTokenModel extends Model
{
    protected $name = 'fb_bm_token';
    protected $autoWriteTimestamp = true;


    public function getPersonalbmTokenIdsAttr($value, $data): array
    {
        if(empty($value)) return [];
        return array_map('intval', explode(',', $value));
    }

    public function setPersonalbmTokenIdsAttr($value): string
    {
        return implode(',', $value);
    }

}