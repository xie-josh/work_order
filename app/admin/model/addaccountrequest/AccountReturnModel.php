<?php

namespace app\admin\model\addaccountrequest;

use think\Model;

/**
 * Transfer
 */
class AccountReturnModel extends Model
{
    // 表名
    protected $name = 'account_return';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function account()
    {
        return $this->belongsTo(\app\admin\model\Account::class, 'account_id', 'account_id');
    }

    public function accountrequestProposal()
    {
        return $this->belongsTo(\app\admin\model\addaccountrequest\AccountrequestProposal::class, 'account_id', 'account_id');
    }
    
}