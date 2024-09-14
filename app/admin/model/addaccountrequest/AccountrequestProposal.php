<?php

namespace app\admin\model\addaccountrequest;

use think\Model;

/**
 * AccountrequestProposal
 */
class AccountrequestProposal extends Model
{
    // 表名
    protected $name = 'accountrequest_proposal';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function affiliationAdmin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'affiliation_admin_id', 'id');
    }

    public function cards(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\card\CardsInfoModel::class, 'cards_id', 'cards_id');
    }

}