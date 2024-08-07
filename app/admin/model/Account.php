<?php

namespace app\admin\model;

use app\admin\model\addaccountrequest\AccountrequestProposal;
use think\Model;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $name = 'account';

    protected $append = ['uuid'];

    protected $ud  = 'KH';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function getMoneyAttr($value): float
    {
        return (float)$value;
    }


    public function getUuidAttr($value,$data)
    {
        return $this->ud.str_pad($data['id'], 6, '0', STR_PAD_LEFT);
    }

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }

    public function accountrequestProposal()
    {
        return $this->hasOne(AccountrequestProposal::class,'account_id','account_id');
    }
}