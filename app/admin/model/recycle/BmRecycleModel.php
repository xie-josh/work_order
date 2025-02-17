<?php

namespace app\admin\model\recycle;

use think\Model;

/**
 * BmRecycleModel
 */
class BmRecycleModel extends Model
{
    // 表名
    protected $name = 'bm_recycle';
    protected $append = ['uuid'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function getUuidAttr($value,$data)
    {
        return $data['id'];
    }

}