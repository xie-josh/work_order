<?php

namespace app\admin\model\demand;

use think\Model;

/**
 * Recharge
 */
class Recharge extends Model
{
    // 表名
    protected $name = 'recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $append = ['uuid'];
    protected $ud  = 'CZ';

    public function getUuidAttr($value,$data)
    {
        return $this->ud.str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    }

    public function getNumberAttr($value): float
    {
        return (float)$value;
    }


    public function accountrequestProposal(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\addaccountrequest\AccountrequestProposal::class, 'account_id', 'account_id');
    }

    public function account(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Account::class, 'account_id', 'account_id');
    }
}