<?php

namespace app\admin\model\addaccountrequest;

use think\Model;
use think\facade\Db;

/**
 * AccountrequestProposal
 */
class AccountrequestProposalTrusteeship extends Model
{
    // 表名
    protected $name = 'accountrequest_proposal_trusteeship';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    //protected $append = ['serial_name'];


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
    public function affiliationAdmin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'affiliation_admin_id', 'id');
    }

    public function account(){
        return $this->belongsTo(\app\admin\model\Account::class, 'account_id', 'account_id');
    }
}